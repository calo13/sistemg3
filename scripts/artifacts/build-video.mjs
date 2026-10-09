/**
 * MemoryLab: captures real Laravel/Livewire actions and records a 180 s WebM.
 * Run from the repository root: node scripts/artifacts/build-video.mjs
 * --probe checks the encoder; --capture-only saves scenes; --encode-only reuses them.
 * Requires local XAMPP/Chrome and the repository's CDP verification helper.
 */
import crypto from 'node:crypto';
import fs from 'node:fs/promises';
import path from 'node:path';
import { pathToFileURL } from 'node:url';
import { artisan, browserClient, delay } from './browser-client.mjs';

const root = process.cwd();
const output = path.join(root, 'output', 'video');
const captures = path.join(output, 'capturas');
const videoName = 'memorylab-demostracion.webm';
const durationSeconds = 180;
const storyboardFile = path.join(output, 'storyboard.json');
const tables = ['scenarios', 'memory_configurations', 'processes', 'memory_frames', 'pages', 'segments', 'simulation_events'];
const signature = () => artisan(`$taskState=[];foreach(${JSON.stringify(tables).replaceAll('"', "'")}as$taskTable){$taskState[$taskTable]=\\Illuminate\\Support\\Facades\\DB::table($taskTable)->orderBy('id')->get()->all();}$taskState['users']=\\App\\Models\\User::orderBy('id')->get()->toArray();echo hash('sha256',json_encode($taskState));`);

await fs.mkdir(captures, { recursive: true });

async function codecProbe(browser) {
    const available = await browser.evaluate(`['video/webm;codecs=vp9','video/webm;codecs=vp8'].filter(type=>window.MediaRecorder&&MediaRecorder.isTypeSupported(type))`);
    if (!available.length) throw new Error('Chrome no ofrece un codificador WebM compatible.');
    return available[0];
}

async function captureActions() {
    const email = `memorylab-video-${crypto.randomUUID()}@example.test`;
    const password = crypto.randomBytes(24).toString('hex') + 'aA1!';
    const baseline = signature();
    let browser, fixture;
    const scenes = [];
    try {
        fixture = JSON.parse(artisan(`$taskUser=\\App\\Models\\User::create(['name'=>'Demostración MemoryLab','email'=>'${email}','password'=>\\Illuminate\\Support\\Facades\\Hash::make('${password}')]);$taskUser->assignRole('administrador');$taskDemo=app(\\App\\Services\\SimulationService::class)->startDemo($taskUser);echo json_encode(['user'=>$taskUser->id,'demo'=>$taskDemo]);`));
        browser = await browserClient();
        console.log('Codificador compatible: ' + await codecProbe(browser));
        await browser.command('Emulation.setDeviceMetricsOverride', { width: 1280, height: 900, deviceScaleFactor: 1, mobile: false });
        await browser.navigate('/login');
        // Private login happens before captures; credentials are never written to artifacts.
        await browser.evaluate(`document.getElementById('email').value=${JSON.stringify(email)};document.getElementById('password').value=${JSON.stringify(password)};document.querySelector('form').requestSubmit();`);
        await browser.waitFor("location.pathname.endsWith('/dashboard')&&document.readyState==='complete'");
        const shot = async (file, selector, offset = 0, height = 500) => {
            await browser.evaluate('document.fonts.ready', true);
            const clip = await browser.evaluate(`(()=>{const e=document.querySelector(${JSON.stringify(selector)});if(!e)throw new Error('Panel no encontrado');const r=e.getBoundingClientRect();return{x:Math.max(0,r.x+scrollX),y:Math.max(0,r.y+scrollY+${offset}),width:Math.min(r.width,1280),height:Math.min(Math.max(1,r.height-${offset}),${height}),scale:1}})()`);
            const captured = await browser.command('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true, clip });
            await fs.writeFile(path.join(captures, file), Buffer.from(captured.data, 'base64'));
            return { file: 'capturas/' + file, width: clip.width, height: clip.height, selector, offset };
        };
        const scene = async (title, caption, seconds, panels, route) => {
            const index = scenes.length + 1;
            const media = [];
            for (let i = 0; i < panels.length; i++) {
                const p = panels[i];
                media.push(await shot(`${String(index).padStart(2,'0')}-${i + 1}.png`, p.selector, p.offset ?? 0, p.height ?? 500));
            }
            scenes.push({ title, caption, seconds, route, media });
            console.log(`Captura ${index}: ${title}`);
        };
        const selectAndWait = async (id, value, condition) => {
            if (await browser.evaluate(`document.getElementById(${JSON.stringify(id)}).value`) !== String(value)) await browser.select(id, value);
            if (id.endsWith('scenario')) await browser.waitFor(`(()=>{let e=document.getElementById(${JSON.stringify(id)});while(e&&!e.hasAttribute('wire:snapshot'))e=e.parentElement;return e&&String(JSON.parse(e.getAttribute('wire:snapshot')).data.scenarioId)===${JSON.stringify(String(value))}})()`);
            if (condition) await browser.waitFor(condition);
        };
        const submitInput = async (id, value) => browser.evaluate(`document.getElementById(${JSON.stringify(id)}).value=${JSON.stringify(String(value))};document.getElementById(${JSON.stringify(id)}).dispatchEvent(new Event('input',{bubbles:true}));document.getElementById(${JSON.stringify(id)}).closest('form').requestSubmit()`);
        await scene('MemoryLab · Sistemas Operativos 1', 'Universidad Mariano Gálvez · Ingeniería en Sistemas. Grupo 3 presenta un simulador interactivo de administración de memoria.', 8, [{ selector: '[aria-labelledby=academic-title]' }], '/dashboard');
        await scene('Integrantes del proyecto académico', 'La aplicación presenta a los cinco integrantes y sus carnés. Los módulos organizan escenarios, procesos y estructuras de memoria.', 6, [{ selector: '#equipo' }], '/dashboard');

        await browser.navigate('/presentation');
        await selectAndWait('paging-scenario', fixture.demo.paging_scenario_id, "!document.getElementById('paging-process').disabled&&document.getElementById('paging-process').options.length===5");
        await browser.select('paging-process', fixture.demo.paging_process_id);
        await browser.waitFor("document.querySelectorAll('[data-paging-page]').length===4&&!!document.getElementById('cpu-page')");
        await scene('Escenario real de paginación', 'RAM: 16 KB, páginas: 1 KB. Chrome tiene cuatro páginas: P0 y P1 están presentes; P2 y P3 permanecen en DISCO.', 10, [{ selector: '[data-paging-panel=pages]' }, { selector: '[data-paging-panel=ram]' }], '/presentation');
        await browser.evaluate("document.getElementById('cpu-page').closest('form').requestSubmit()");
        await browser.waitFor("document.querySelector('[data-cpu-outcome]')?.dataset.cpuOutcome==='PAGE_HIT'");
        await scene('PAGE_HIT: la página ya está presente', 'La CPU solicita Chrome P0. La tabla apunta al marco 0: el acceso se resuelve sin cargar otra página ni aumentar los Page Faults.', 10, [{ selector: '[data-cpu-result]' }, { selector: '[data-paging-panel=pages]' }], '/presentation');
        await browser.select('cpu-mode', 'step');
        await browser.waitFor("(()=>{let e=document.getElementById('cpu-mode');while(e&&!e.hasAttribute('wire:snapshot'))e=e.parentElement;return e&&JSON.parse(e.getAttribute('wire:snapshot')).data.mode==='step'})()");
        await browser.select('cpu-page', 3);
        await browser.waitFor("document.getElementById('cpu-page').value==='3'");
        await browser.evaluate("document.getElementById('cpu-page').closest('form').requestSubmit()");
        const current = step => `document.querySelector('[aria-current=step]')?.dataset.flowIndex==='${step}'`;
        await browser.waitFor(current(1));
        const flow = async (title, caption, seconds, offset = 0) => scene(title, caption, seconds, [{ selector: '[data-memory-flow]', offset }, { selector: '[data-paging-panel=ram]' }], '/presentation');
        await flow('Paso 1 · La CPU solicita P3', 'El modo paso a paso registra la solicitud. La RAM conserva sus cinco marcos ocupados mientras se explica el recorrido.', 8);
        const advance = async step => {
            await browser.evaluate("document.querySelector('[data-next-step]').click()");
            await browser.waitFor(current(step));
        };
        await advance(2); await advance(3);
        await flow('Paso 3 · P3 no está presente', 'La tabla confirma que Chrome P3 está en DISCO. Se identifica un Page Fault; todavía no se modifica la RAM.', 8);
        await advance(4); await advance(5);
        await flow('Paso 5 · Buscar un marco disponible', 'El simulador comprueba los marcos libres. La carga utiliza el primer marco libre; si la RAM se llena, aplica reemplazo FIFO.', 8);
        await advance(6);
        await browser.waitFor("document.querySelector('[data-cpu-outcome]')?.dataset.cpuOutcome==='PAGE_FAULT'");
        await browser.waitFor("document.querySelectorAll('[data-frame-state=OCCUPIED]').length===6");
        await flow('Paso 6 · Cargar desde DISCO a RAM', 'P3 se carga en el marco 5. Se registran PAGE_FAULT y PAGE_LOADED: ahora hay seis marcos ocupados y seis fallos acumulados.', 10);
        await advance(7); await advance(8);
        await scene('Paso 8 · Acceso completado', 'La tabla y la RAM muestran el mismo estado: Chrome P3 está presente en el marco 5. El acceso queda registrado una sola vez.', 8, [{ selector: '[data-cpu-result]' }, { selector: '[data-paging-panel=pages]' }], '/presentation');
        await scene('RAM y almacenamiento secundario', 'Cada marco guarda una página de un proceso. Las páginas no residentes se conservan en el almacenamiento secundario simulado.', 10, [{ selector: '[data-paging-panel=ram]' }, { selector: '[data-paging-panel=secondary]' }], '/presentation');

        await browser.navigate('/traduccion');
        await selectAndWait('translation-scenario', fixture.demo.paging_scenario_id, "!document.getElementById('translation-process').disabled");
        await browser.select('translation-process', fixture.demo.paging_process_id);
        await browser.waitFor("!document.getElementById('logical-address').closest('form').querySelector('button').disabled");
        await submitInput('logical-address', 3500);
        await browser.waitFor("document.querySelector('[data-translation-physical]')?.textContent==='5548'");
        await scene('Traducción: página y offset', '3500 ÷ 1024 da página 3 y offset 428. La tabla asigna el marco 5: dirección física = 5 × 1024 + 428 = 5548 bytes.', 12, [{ selector: '[data-translation-result]' }], '/traduccion');

        await browser.navigate('/segmentacion');
        await selectAndWait('segment-scenario', fixture.demo.segmentation_scenario_id, "!document.getElementById('segment-process').disabled");
        await browser.select('segment-process', fixture.demo.segmentation_process_id);
        await browser.waitFor("document.querySelectorAll('[data-segment-number]').length===4&&!!document.getElementById('segment-offset')");
        await scene('Segmentación: código, datos, pila y heap', 'Los segmentos tienen tamaño variable y guardan su base y límite. El proceso Editor ocupa cuatro intervalos de la RAM.', 10, [{ selector: '[data-segment-table]' }, { selector: '[data-segment-map]', height: 500 }], '/segmentacion');
        await submitInput('segment-offset', 100);
        await browser.waitFor("document.querySelector('[data-segment-physical]')?.textContent==='1100'");
        await scene('Acceso válido: base + offset', 'Código comienza en la base 1000 y su límite es 1200 bytes. Offset 100 es válido: dirección física = 1000 + 100 = 1100.', 10, [{ selector: '[data-segment-access]' }], '/segmentacion');
        await submitInput('segment-offset', 1200);
        await browser.waitFor("document.querySelector('[data-segment-outcome]')?.dataset.segmentOutcome==='SEGMENTATION_FAULT'");
        await scene('SEGMENTATION_FAULT: fuera del límite', 'El offset 1200 no cumple offset < límite. El acceso se rechaza, no existe dirección física y la ocupación de RAM se conserva.', 12, [{ selector: '[data-segment-access]' }], '/segmentacion');

        await browser.navigate('/comparacion');
        await browser.waitFor("document.querySelector('[data-comparison-request]').textContent==='7168'");
        await scene('Contigua frente a no contigua', 'Hay 9 KB libres, separados en huecos de 3 y 6 KB. Una solicitud de 7 KB falla en contigua; paginación y segmentación pueden distribuirla.', 12, [{ selector: '[data-comparison-mode=contiguous]' }, { selector: '[data-comparison-mode=paging]' }, { selector: '[data-comparison-mode=segmentation]' }], '/comparacion');
        await submitInput('comparison-bytes', 7500);
        await browser.waitFor("document.querySelector('[data-comparison-mode=paging] [data-comparison-waste]').textContent==='692 B'");
        await scene('Fragmentación interna y externa', '7500 bytes requieren ocho páginas: reserva 8192 y deja 692 bytes internos sin usar. Los huecos separados explican la fragmentación externa.', 10, [{ selector: '[data-comparison-mode=contiguous]' }, { selector: '[data-comparison-mode=paging]' }, { selector: '[data-comparison-mode=segmentation]' }], '/comparacion');

        await browser.navigate('/terminal');
        await selectAndWait('terminal-scenario', fixture.demo.paging_scenario_id, "!document.getElementById('terminal-command').closest('form').querySelector('button[type=submit]').disabled");
        for (const command of ['memory status', 'page table chrome', 'request chrome 3']) {
            await submitInput('terminal-command', command);
            await browser.waitFor(`document.querySelector('[data-terminal-output]').textContent.includes(${JSON.stringify('> ' + command)})&&document.getElementById('terminal-command').value===''`);
        }
        await scene('Terminal educativa', 'Los comandos consultan las mismas tablas y servicios. “request chrome 3” produce PAGE_HIT porque P3 ya fue cargada durante el recorrido.', 10, [{ selector: '[data-terminal-output]' }], '/terminal');
        await browser.navigate('/historial');
        await selectAndWait('history-scenario', fixture.demo.paging_scenario_id, "document.querySelectorAll('[data-history-event]').length>0");
        await browser.select('history-process', fixture.demo.paging_process_id);
        await browser.waitFor("document.querySelectorAll('[data-history-event]').length>0&&Array.from(document.querySelectorAll('[data-history-event]')).every(e=>e.textContent.includes('Chrome'))");
        await browser.evaluate("document.querySelector('[data-history-event]').closest('.card').setAttribute('data-video-history','');true");
        await scene('Historial: evidencia de cada acceso', 'Cada solicitud, HIT y Page Fault conserva proceso, actor y fecha. Los filtros permiten seguir el comportamiento real del escenario.', 8, [{ selector: '[data-video-history]', height: 500 }], '/historial');
        await browser.navigate('/presentation');
        await selectAndWait('paging-scenario', fixture.demo.paging_scenario_id, "!document.getElementById('paging-process').disabled");
        await browser.select('paging-process', fixture.demo.paging_process_id);
        await browser.waitFor("document.querySelector('[data-cpu-outcome]')?.dataset.cpuOutcome==='PAGE_HIT'");
        await scene('Conclusión · Entender las estructuras', 'La tabla traduce páginas a marcos; base y límite protegen segmentos. La distribución aprovecha la RAM y el historial permite comprobar cada resultado.', 10, [{ selector: '[data-paging-panel=pages]' }, { selector: '[data-paging-panel=ram]' }], '/presentation');

        if (scenes.reduce((n,s)=>n+s.seconds,0) !== durationSeconds) throw new Error('Guion con duración incorrecta.');
        if (browser.errors.length || browser.failedResources.filter(r=>!r.endsWith('/favicon.ico')).length) throw new Error('Se detectaron errores en los recursos de la aplicación.');
        await fs.writeFile(storyboardFile, JSON.stringify({ duration_seconds: durationSeconds, width: 1280, height: 720, scenes }, null, 2));
        return scenes;
    } catch(error) {
        if(browser) console.log(await browser.evaluate("JSON.stringify({route:location.pathname,validation:Array.from(document.querySelectorAll('.invalid-feedback,.alert-danger'),e=>e.textContent.trim()),terminal:document.querySelector('[data-terminal-output]')?.textContent?.slice(-1000)})"));
        throw error;
    } finally {
        if (browser) await browser.close();
        // Delete only the generated user and its validated demo pair, never seed data.
        artisan(`$taskUser=\\App\\Models\\User::where('email','${email}')->first();if($taskUser){\\Illuminate\\Support\\Facades\\DB::transaction(function()use($taskUser){foreach(\\App\\Models\\Scenario::where('created_by',$taskUser->id)->get()as$taskScenario){if(!$taskScenario->is_demo||!str_starts_with($taskScenario->name,'Demostración de '))throw new \\RuntimeException('Escenario temporal inesperado');$taskScenario->events()->delete();$taskScenario->pages()->delete();$taskScenario->segments()->delete();$taskScenario->frames()->delete();$taskScenario->processes()->delete();$taskScenario->configuration()->delete();$taskScenario->delete();}\\Illuminate\\Support\\Facades\\DB::table('sessions')->where('user_id',$taskUser->id)->delete();$taskUser->delete();});}`);
        if (signature() !== baseline) throw new Error('El dominio no coincide con el estado anterior a la captura.');
        console.log('Limpieza verificada: escenarios, eventos, cuenta y sesiones temporales eliminados; datos originales intactos.');
    }
}

// EBML Duration is measured in Segment Ticks; TimecodeScale defaults to 1,000,000 ns.
// https://www.matroska.org/technical/elements.html (Info, TimestampScale, Duration)
function element(buffer, position) {
    const vint = (offset, id = false) => {
        const first = buffer[offset];
        if (!first) throw new Error('EBML inválido.');
        let length = 1, mask = 0x80;
        while (!(first & mask)) { length++; mask >>= 1; }
        let value = BigInt(id ? first : first & (mask - 1));
        for (let i=1;i<length;i++) value = value * 256n + BigInt(buffer[offset+i]);
        return { length, value, unknown: !id && value === (1n << BigInt(7*length))-1n };
    };
    const id = vint(position, true), size = vint(position + id.length);
    const start = position + id.length + size.length;
    return { id: Number(id.value), position, sizePosition: position + id.length, sizeLength: size.length, start, end: size.unknown ? buffer.length : start + Number(size.value), size: Number(size.value), unknown: size.unknown };
}
function sizeVint(value, length) {
    let n = BigInt(value) | (1n << BigInt(7*length));
    const b = Buffer.alloc(length);
    for(let i=length-1;i>=0;i--) { b[i]=Number(n & 255n); n >>= 8n; }
    return b;
}
async function finiteDuration(file, seconds) {
    let buffer = await fs.readFile(file);
    let segment, info;
    for(let p=0;p<buffer.length;) { const e=element(buffer,p);if(e.id===0x18538067){segment=e;break;}p=e.end; }
    if(!segment) throw new Error('El video no contiene un segmento WebM.');
    for(let p=segment.start;p<segment.end;) {const e=element(buffer,p);if(e.id===0x1549a966){info=e;break;}p=e.end;}
    if(!info) throw new Error('El video no contiene información WebM.');
    let scale=1000000, existing;
    for(let p=info.start;p<info.end;) {const e=element(buffer,p);if(e.id===0x2ad7b1){scale=0;for(let i=e.start;i<e.end;i++)scale=scale*256+buffer[i];}if(e.id===0x4489)existing=e;p=e.end;}
    const ticks=seconds*1e9/scale;
    if(existing) {
        if(existing.size===8)buffer.writeDoubleBE(ticks,existing.start);
        else if(existing.size===4)buffer.writeFloatBE(ticks,existing.start);
        else throw new Error('Duration WebM tiene tamaño inesperado.');
    } else {
        const duration=Buffer.alloc(11);duration.set([0x44,0x89,0x88]);duration.writeDoubleBE(ticks,3);
        const newSize=sizeVint(info.size+duration.length,info.sizeLength);
        buffer=Buffer.concat([buffer.subarray(0,info.sizePosition),newSize,buffer.subarray(info.start,info.end),duration,buffer.subarray(info.end)]);
        if(!segment.unknown)sizeVint(segment.size+duration.length,segment.sizeLength).copy(buffer,segment.sizePosition);
    }
    await fs.writeFile(file,buffer);
}

async function encode(scenes) {
    const browser = await browserClient();
    try {
        const mime = await codecProbe(browser);
        const finalPath=path.join(output,videoName);
        let recorderPath;
        if(!process.argv.includes('--verify-only')) {
        const mediaScenes = [];
        for(const s of scenes){const media=[];for(const m of s.media)media.push({...m,src:'data:image/png;base64,'+(await fs.readFile(path.join(output,m.file))).toString('base64')});mediaScenes.push({...s,media});}
        const html = `<!doctype html><html lang="es"><meta charset="utf-8"><title>Grabación MemoryLab</title><body style="margin:0;background:#0f172a"><canvas id="video" width="1280" height="720"></canvas><script>
const scenes=${JSON.stringify(mediaScenes)},total=${durationSeconds},mime=${JSON.stringify(mime)};
window.recording={ready:false,done:false,elapsed:0,error:null,mime};
const c=document.getElementById('video'),ctx=c.getContext('2d'),images=[];
const load=src=>new Promise((resolve,reject)=>{const image=new Image();image.onload=()=>resolve(image);image.onerror=reject;image.src=src});
function wrap(text,x,y,width,size=26){ctx.font='500 '+size+'px Arial';ctx.fillStyle='#f8fafc';let line='',row=0;for(const word of text.split(' ')){const next=line?line+' '+word:word;if(ctx.measureText(next).width>width&&line){ctx.fillText(line,x,y+row*(size+8));line=word;row++}else line=next;}ctx.fillText(line,x,y+row*(size+8));}
function draw(t){let start=0,index=0;for(let i=0;i<scenes.length;i++){if(t<start+scenes[i].seconds||i===scenes.length-1){index=i;break;}start+=scenes[i].seconds;}const scene=scenes[index];ctx.fillStyle='#10172a';ctx.fillRect(0,0,1280,720);ctx.fillStyle='#8b83ff';ctx.fillRect(0,0,8,720);ctx.font='700 29px Arial';ctx.fillStyle='#ffffff';ctx.fillText(scene.title,28,45);ctx.font='15px Arial';ctx.fillStyle='#a8b3cf';ctx.fillText('MEMORYLAB · UMG · INGENIERÍA EN SISTEMAS · GRUPO 3',28,73);ctx.textAlign='right';ctx.fillText(String(Math.floor(t/60)).padStart(2,'0')+':'+String(Math.floor(t%60)).padStart(2,'0')+' / 03:00',1252,72);ctx.textAlign='left';const gap=18,areaW=1224,areaH=488,slotW=(areaW-gap*(scene.media.length-1))/scene.media.length;for(let j=0;j<images[index].length;j++){const image=images[index][j],scale=Math.min(slotW/image.width,areaH/image.height),w=image.width*scale,h=image.height*scale,x=28+j*(slotW+gap)+(slotW-w)/2,y=94+(areaH-h)/2;ctx.fillStyle='#eff2f7';ctx.fillRect(28+j*(slotW+gap),94,slotW,areaH);ctx.drawImage(image,x,y,w,h);}ctx.fillStyle='#202a42';ctx.fillRect(24,597,1232,105);wrap(scene.caption,42,632,1196,26);ctx.fillStyle='#7771fa';ctx.fillRect(0,713,1280*Math.min(t/total,1),7);window.recording.elapsed=t;}
Promise.all(scenes.map(async s=>Promise.all(s.media.map(m=>load(m.src))))).then(all=>{images.push(...all);draw(0);window.recording.ready=true;window.startRecording=()=>{const stream=c.captureStream(15),recorder=new MediaRecorder(stream,{mimeType:mime,videoBitsPerSecond:3000000}),chunks=[];recorder.ondataavailable=e=>{if(e.data.size)chunks.push(e.data)};recorder.onerror=e=>{window.recording.error=e.error.message};recorder.onstop=()=>{const blob=new Blob(chunks,{type:mime}),a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download=${JSON.stringify(videoName)};a.click();window.recording.done=true;window.recording.bytes=blob.size;stream.getTracks().forEach(track=>track.stop());};const began=performance.now();recorder.start(1000);const timer=setInterval(()=>{const elapsed=(performance.now()-began)/1000;draw(Math.min(elapsed,total));if(elapsed>=total){clearInterval(timer);recorder.stop();}},1000/15);};}).catch(e=>window.recording.error=String(e));
</script></body></html>`;
        recorderPath = path.join(output,'grabador.html');
        await fs.writeFile(recorderPath,html);
        try{await fs.unlink(finalPath);}catch(e){if(e.code!=='ENOENT')throw e;}
        await browser.command('Browser.setDownloadBehavior',{behavior:'allow',downloadPath:output});
        await browser.command('Page.navigate',{url:pathToFileURL(recorderPath).href});
        await browser.waitFor('window.recording?.ready===true');
        await browser.evaluate('window.startRecording();true');
        while(true){await delay(15000);const state=await browser.evaluate('window.recording');if(state.error)throw new Error('Error al grabar: '+state.error);console.log('Grabación: '+Math.min(180,Math.floor(state.elapsed))+' / 180 s');if(state.done)break;}
        for(let i=0;i<100;i++){try{await fs.stat(finalPath);break;}catch{await delay(100);}}
        await finiteDuration(finalPath,durationSeconds);
        console.log('WebM guardado con duración finita.');
        }
        const qaHtml=`<!doctype html><html lang="es"><meta charset="utf-8"><title>MemoryLab · Video de demostración</title><style>body{margin:0;background:#10172a;color:white;font-family:Arial}h1,p{margin:16px 24px}video{display:block;width:min(1280px,100%);height:auto}</style><h1>MemoryLab · Demostración académica</h1><p>Universidad Mariano Gálvez · Ingeniería en Sistemas · Grupo 3 · 3 minutos</p><video controls preload="metadata" src="${videoName}"></video></html>`;
        const qaPath=path.join(output,'verificar-video.html');await fs.writeFile(qaPath,qaHtml);
        await browser.command('Page.navigate',{url:pathToFileURL(qaPath).href});
        await browser.waitFor("document.querySelector('video')?.readyState>=1");
        const metadata=await browser.evaluate("(()=>{const v=document.querySelector('video');return{duration:v.duration,width:v.videoWidth,height:v.videoHeight,codec:window.MediaRecorder.isTypeSupported('video/webm;codecs=vp9')?'VP9':'VP8'}})()");
        if(!Number.isFinite(metadata.duration)||Math.abs(metadata.duration-180)>1||metadata.width!==1280||metadata.height!==720)throw new Error('Metadatos del video incorrectos.');
        await browser.evaluate("document.querySelector('video').controls=false;true");
        const checks=[];
        for(const second of[2,46,62,93,122,145,157,168,176]){
            await browser.evaluate(`(()=>{window.videoSeekDone=false;const v=document.querySelector('video');v.onseeked=()=>window.videoSeekDone=true;v.currentTime=${second};})()`);
            await browser.waitFor('window.videoSeekDone===true');
            await delay(100);
            const clip=await browser.evaluate("(()=>{const r=document.querySelector('video').getBoundingClientRect();return{x:r.x+scrollX,y:r.y+scrollY,width:r.width,height:r.height,scale:1}})()");
            const frame=await browser.command('Page.captureScreenshot',{format:'png',captureBeyondViewport:true,clip});
            const file=`qa-${String(second).padStart(3,'0')}.png`;await fs.writeFile(path.join(output,file),Buffer.from(frame.data,'base64'));checks.push({second,file});
        }
        await browser.evaluate("document.querySelector('video').muted=true;document.querySelector('video').play().then(()=>true)",true);
        const before=await browser.evaluate("document.querySelector('video').currentTime");await delay(1200);const after=await browser.evaluate("document.querySelector('video').currentTime");
        if(after<=before)throw new Error('La reproducción no avanza.');
        const stat=await fs.stat(finalPath);
        const report={...metadata,mime,bytes:stat.size,sha256:crypto.createHash('sha256').update(await fs.readFile(finalPath)).digest('hex'),scenes:scenes.length,captures:scenes.reduce((n,s)=>n+s.media.length,0),checks,playback_verified:true,temporary_data_cleaned:true};
        await fs.writeFile(path.join(output,'verificacion.json'),JSON.stringify(report,null,2));
        console.log(JSON.stringify(report));
        // The recorder page embeds source PNGs and is an intermediate artifact only.
        if(recorderPath)await fs.unlink(recorderPath);
    } finally {await browser.close();}
}

if(process.argv.includes('--probe')){
    const browser=await browserClient();try{console.log(await codecProbe(browser));}finally{await browser.close();}
}else{
    const scenes=process.argv.includes('--encode-only')||process.argv.includes('--verify-only')?JSON.parse(await fs.readFile(storyboardFile,'utf8')).scenes:await captureActions();
    if(!process.argv.includes('--capture-only')) await encode(scenes);
}
