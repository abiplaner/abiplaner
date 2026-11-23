/**
 * Ersatz für Inline-Handler (Modul 7.2).
 *
 * Alles hier hängt an genau einem Listener auf `document` und wertet
 * data-Attribute aus. Der Grund ist die CSP: ein `onclick="…"` im HTML ist für
 * den Browser ein Inline-Skript und fällt, sobald eine Nonce im script-src
 * steht. Ein data-Attribut ist Text und bleibt unberührt.
 *
 * Verfügbare Attribute:
 *   data-confirm="Frage"   an <form>  → fragt vor dem Absenden
 *                          an <button>/<a> → fragt vor der Aktion
 *   data-submit="form-id"  zusätzlich an <button> → sendet dieses Formular ab
 *                          (für Knöpfe, die außerhalb ihres Formulars stehen)
 *   data-submit-on-change  an <select>/<input> → sendet das eigene Formular
 *                          ab, sobald sich der Wert ändert (Filterleisten)
 *   data-navigate-on-change  an <select> → lädt die Seite mit geändertem
 *                          Abfrageparameter neu; dazu:
 *   data-param="name"      der Parameter, der den Wert bekommt
 *   data-url="…"           optionale Basis-Adresse; ohne sie die aktuelle
 *   data-modal-open="id"   blendet das Element mit dieser id ein,
 *   data-modal-close="id"  blendet es aus,
 *   data-modal-toggle="id" schaltet um (jeweils über die Klasse `hidden`)
 *   data-submit-parent     an <a> → schickt statt der Verlinkung das
 *                          umgebende Formular ab (Abmelden, Zeitraumwechsel)
 */

// Formulare: die Frage hängt am <form> selbst.
document.addEventListener('submit', (ereignis) => {
    const traeger = ereignis.target.closest('[data-confirm]');

    if (traeger && !window.confirm(traeger.dataset.confirm)) {
        ereignis.preventDefault();
    }
});

// Knöpfe und Links: die Frage hängt am Element selbst.
document.addEventListener('click', (ereignis) => {
    const traeger = ereignis.target.closest('[data-confirm], [data-submit]');

    if (!traeger) {
        return;
    }

    // Ein Klick irgendwo in einem Formular mit data-confirm findet über
    // closest() das Formular — dort fragt aber schon der submit-Listener.
    // Ohne diese Weiche käme die Frage bei jedem Klick ins Eingabefeld.
    if (traeger.tagName === 'FORM') {
        return;
    }

    if (traeger.dataset.confirm && !window.confirm(traeger.dataset.confirm)) {
        ereignis.preventDefault();
        ereignis.stopPropagation();

        return;
    }

    if (traeger.dataset.submit) {
        ereignis.preventDefault();
        document.getElementById(traeger.dataset.submit)?.submit();
    }
});

// Filterleisten: eine Auswahl ändern heißt, das Formular abzuschicken.
document.addEventListener('change', (ereignis) => {
    const traeger = ereignis.target.closest('[data-submit-on-change]');

    if (!traeger) {
        return;
    }

    // Bewusst submit() und nicht requestSubmit(): Der frühere Inline-Handler
    // hat ebenfalls direkt abgeschickt, ohne submit-Ereignis und ohne
    // HTML-Validierung. requestSubmit() würde beides einschalten und könnte
    // Filterleisten stillstehen lassen, die ein Pflichtfeld enthalten.
    const formular = traeger.form ?? traeger.closest('form');

    formular?.submit();
});

// Auswahlfelder, die statt eines Formulars nur die Adresse ändern.
document.addEventListener('change', (ereignis) => {
    const traeger = ereignis.target.closest('[data-navigate-on-change]');

    if (!traeger || !traeger.dataset.param) {
        return;
    }

    const ziel = new URL(traeger.dataset.url || window.location.href, window.location.href);

    ziel.searchParams.set(traeger.dataset.param, traeger.value);
    window.location.href = ziel.toString();
});

// Links, die in Wahrheit ein Formular abschicken (Abmelden, Zeitraumwechsel).
document.addEventListener('click', (ereignis) => {
    const ausloeser = ereignis.target.closest('[data-submit-parent]');

    if (!ausloeser) {
        return;
    }

    ereignis.preventDefault();
    ausloeser.closest('form')?.submit();
});

// Ein- und Ausblenden von Dialogen über die Klasse `hidden`.
document.addEventListener('click', (ereignis) => {
    const traeger = ereignis.target.closest(
        '[data-modal-open], [data-modal-close], [data-modal-toggle]'
    );

    if (!traeger) {
        return;
    }

    const { modalOpen, modalClose, modalToggle } = traeger.dataset;
    const dialog = document.getElementById(modalOpen || modalClose || modalToggle);

    if (dialog === null) {
        return;
    }

    if (modalOpen) {
        dialog.classList.remove('hidden');
    } else if (modalClose) {
        dialog.classList.add('hidden');
    } else {
        dialog.classList.toggle('hidden');
    }
});
