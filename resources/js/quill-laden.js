/**
 * Sammelstelle für Quill samt Stylesheet (Modul 7.2).
 *
 * Diese Datei wird nur dynamisch importiert. Vite schnürt daraus einen
 * eigenen Chunk, der erst geladen wird, wenn wirklich ein Editor auf der
 * Seite steht — die übrigen Masken zahlen die rund 200 kB nicht mit.
 */
import Quill from 'quill';
import 'quill/dist/quill.snow.css';

export default Quill;
