import 'leaflet/dist/leaflet.css';

import { createRoot } from 'react-dom/client';

import { App } from './app';

const raiz = document.getElementById('pwa');

if (raiz) {
    createRoot(raiz).render(<App />);
}
