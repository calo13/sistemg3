import './bootstrap';
import '@fontsource/public-sans/latin-400.css';
import '@fontsource/public-sans/latin-500.css';
import '@fontsource/public-sans/latin-600.css';
import '@fontsource/public-sans/latin-700.css';
import * as bootstrap from 'bootstrap';
import { Livewire } from '../../vendor/livewire/livewire/dist/livewire.esm';
import { initializeSneat } from './sneat';
import './memory-flow';
import './landing';

window.bootstrap = bootstrap;

initializeSneat();
document.addEventListener('livewire:navigated', initializeSneat);

Livewire.start();
