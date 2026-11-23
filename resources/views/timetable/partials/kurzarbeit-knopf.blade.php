{{-- „+ KA": öffnet den Dialog zum Eintragen einer Kurzarbeit. Erwartet $datum (Y-m-d). --}}
<div class="mt-1 text-right">
    <button type="button"
            class="ka-knopf text-[10px] leading-none px-1 py-0.5 rounded border border-emerald-300 text-emerald-700 bg-white hover:bg-emerald-50"
            x-on:click.stop="openKurzarbeitModal('{{ $datum }}')"
            title="Kurzarbeit eintragen">+ KA</button>
</div>
