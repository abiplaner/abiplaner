import Alpine from 'alpinejs';

/**
 * Rich-Text-Editor für die Schuleinstellungen (Modul 7.2).
 *
 * Vorher lag die Komponente als Inline-Skript in der View und Quill selbst
 * auf einem CDN. Seit die CSP scharf ist, wurde die Bibliothek dort
 * blockiert — der Editor erschien nicht, und weil das versteckte Feld erst
 * von Quill befüllt wird, hätte Speichern die Texte geleert.
 *
 * Aufruf in der View: x-data="quillEditor('schluessel', @js($wert))"
 */
Alpine.data('quillEditor', (schluessel, initialHtml = '') => ({
    async init() {
        const { default: Quill } = await import('./quill-laden.js');

        const editor = new Quill(this.$refs.editor, {
            theme: 'snow',
            modules: {
                toolbar: [
                    ['bold', 'italic'],
                    [{ list: 'bullet' }, { list: 'ordered' }],
                    ['clean'],
                ],
            },
        });

        if (initialHtml) {
            editor.clipboard.dangerouslyPasteHTML(initialHtml);
        }

        // Das versteckte Feld trägt den Wert in den POST. Es ist in der View
        // bereits mit dem gespeicherten Text vorbelegt; hier wird es auf den
        // Stand des Editors gebracht.
        this.$refs.input.value = editor.root.innerHTML;

        editor.on('text-change', () => {
            const html = editor.root.innerHTML;

            // Quills eigene Darstellung von "leer" nicht als Inhalt speichern.
            this.$refs.input.value = (html === '<p><br></p>') ? '' : html;
        });
    },
}));
