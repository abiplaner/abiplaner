import './bootstrap';
import './actions';
import './quill-editor';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

// Chart.js kommt als eigener Chunk und wird nur dort geladen, wo wirklich
// ein Diagramm steht (Modul 7.2 — vorher lag die Bibliothek auf einem CDN
// und wurde von der CSP blockiert).
window.chartJsLaden = () => import('./chart-laden.js').then((modul) => modul.default);

Alpine.start();
