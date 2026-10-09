import {spawn,spawnSync} from 'node:child_process';
import fs from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
export const delay=ms=>new Promise(resolve=>setTimeout(resolve,ms));
export function artisan(code){
    const result=spawnSync('C:\\xampp\\php\\php.exe',['artisan','tinker','--execute',code],{encoding:'utf8',windowsHide:true});
    if(result.status!==0)throw new Error('Falló operación Laravel de verificación.');
    return result.stdout.trim();
}
export async function browserClient(){
    const tempRoot=await fs.realpath(os.tmpdir());
    const profile=await fs.mkdtemp(path.join(tempRoot,'memorylab-browser-'));
    const process=spawn('C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',['--headless=new','--no-first-run','--no-default-browser-check','--disable-background-networking','--remote-debugging-address=127.0.0.1','--remote-debugging-port=0',`--user-data-dir=${profile}`,'about:blank'],{stdio:'ignore',windowsHide:true});
    let port;
    for(let i=0;i<100;i++){try{port=Number((await fs.readFile(path.join(profile,'DevToolsActivePort'),'utf8')).split('\n')[0]);break}catch{await delay(100)}}
    if(!port)throw new Error('Chrome no inició.');
    const target=await(await fetch(`http://127.0.0.1:${port}/json/new?about:blank`,{method:'PUT'})).json();
    const socket=new WebSocket(target.webSocketDebuggerUrl);
    await new Promise((resolve,reject)=>{socket.onopen=resolve;socket.onerror=reject});
    let sequence=0,updates=0;
    const pending=new Map(),errors=[],failedResources=[];
    socket.onmessage=event=>{
        const message=JSON.parse(event.data);
        if(message.method==='Runtime.exceptionThrown')errors.push(message.params.exceptionDetails.text);
        if(message.method==='Network.responseReceived'){
            const pathname=new URL(message.params.response.url).pathname;
            if(message.params.response.status>=400)failedResources.push(pathname);
            if(pathname.endsWith('/livewire/update')&&message.params.response.status===200)updates++;
        }
        if(message.id&&pending.has(message.id)){
            const entry=pending.get(message.id);clearTimeout(entry.timer);pending.delete(message.id);
            message.error?entry.reject(new Error(message.error.message)):entry.resolve(message.result);
        }
    };
    const command=(method,params={})=>new Promise((resolve,reject)=>{
        const id=++sequence,timer=setTimeout(()=>{pending.delete(id);reject(new Error('Tiempo agotado: '+method))},15000);
        pending.set(id,{resolve,reject,timer});socket.send(JSON.stringify({id,method,params}));
    });
    const evaluate=async(expression,awaitPromise=false)=>{
        const result=await command('Runtime.evaluate',{expression,returnByValue:true,awaitPromise});
        if(result.exceptionDetails)throw new Error('Falló evaluación de la página.');
        return result.result.value;
    };
    const waitFor=async expression=>{
        for(let i=0;i<100;i++){if(await evaluate(expression))return;await delay(100)}
        throw new Error('Estado de verificación no alcanzado.');
    };
    const navigate=async route=>{
        await command('Page.navigate',{url:'http://localhost/sistemg3/public'+route});
        await waitFor(`location.pathname.endsWith(${JSON.stringify(route)})&&document.readyState==='complete'&&!!window.Livewire`);
    };
    const size=width=>command('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false});
    const screenshot=async file=>{
        await evaluate('document.fonts.ready',true);
        const{cssContentSize}=await command('Page.getLayoutMetrics');
        const shot=await command('Page.captureScreenshot',{format:'png',captureBeyondViewport:true,clip:{x:0,y:0,width:cssContentSize.width,height:cssContentSize.height,scale:1}});
        await fs.writeFile(file,Buffer.from(shot.data,'base64'));
    };
    const select=(id,value)=>evaluate(`document.getElementById(${JSON.stringify(id)}).value=${JSON.stringify(String(value))};document.getElementById(${JSON.stringify(id)}).dispatchEvent(new Event('change',{bubbles:true}));`);
    const close=async()=>{
        await command('Browser.close').catch(()=>{});socket.close();await delay(400);
        if(process.exitCode===null)process.kill();
        const resolved=await fs.realpath(profile);
        if(path.dirname(resolved)!==tempRoot||!path.basename(resolved).startsWith('memorylab-browser-'))throw new Error('Ruta temporal inválida.');
        await fs.rm(resolved,{recursive:true,force:true,maxRetries:3,retryDelay:200});
    };
    await command('Page.enable');await command('Runtime.enable');await command('Network.enable');await size(1366);
    return{command,evaluate,waitFor,navigate,size,screenshot,select,close,errors,failedResources,get updates(){return updates}};
}
