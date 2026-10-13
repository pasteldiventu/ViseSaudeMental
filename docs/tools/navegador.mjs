import { existsSync } from 'node:fs';

/** Chrome/Chromium/Edge instalado na máquina (ou BROWSER_PATH). */
export function browserPath() {
  const candidatos = [
    process.env.BROWSER_PATH,
    'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
    'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
    'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    '/Applications/Microsoft Edge.app/Contents/MacOS/Microsoft Edge',
    '/usr/bin/google-chrome',
    '/usr/bin/chromium',
    '/usr/bin/chromium-browser',
    '/snap/bin/chromium',
    '/usr/bin/microsoft-edge',
  ].filter(Boolean);
  const encontrado = candidatos.find((p) => existsSync(p));
  if (!encontrado) {
    console.error('Nenhum Chrome/Chromium/Edge encontrado. Defina BROWSER_PATH.');
    process.exit(1);
  }
  return encontrado;
}
