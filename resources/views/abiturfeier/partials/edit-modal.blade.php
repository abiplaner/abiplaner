{{-- Bearbeiten-Modal: Ehrung bearbeiten (in der Archivansicht komplett ausgeblendet) --}}
@unlessarchive
<div x-data="editAwardModal()"
     @open-edit-modal.window="open($event.detail)"
     x-show="show"
     class="fixed inset-0 z-50 flex items-center justify-center"
     style="display:none">

    <div class="absolute inset-0 bg-black bg-opacity-40" @click="show = false"></div>

    <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-lg mx-4 p-6">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-1">Ehrung bearbeiten</h3>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-5">
            <span class="font-medium text-gray-700 dark:text-gray-200" x-text="studentName"></span>
            <span class="mx-1 text-gray-300">·</span>
            <span x-text="awardTypeName"></span>
        </p>

        <form :action="`/abiturfeier/awards/${awardId}`" method="POST" class="space-y-4">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-2 gap-4">
                {{-- Platzierung --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Platzierung
                        <span class="font-normal text-gray-400">(optional)</span>
                    </label>
                    <select name="rank" x-model="rank"
                            class="w-full text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-md shadow-sm">
                        <option value="">— kein Rang —</option>
                        <option value="1">1. Preisträger</option>
                        <option value="2">2. Preisträger</option>
                        <option value="3">3. Preisträger</option>
                    </select>
                </div>

                {{-- Kandidat --}}
                <div class="flex items-end pb-1">
                    <label class="flex items-center space-x-2 cursor-pointer">
                        <input type="hidden" name="is_candidate" value="0">
                        <input type="checkbox" name="is_candidate" value="1"
                               x-model="isCandidate"
                               class="rounded border-gray-300 dark:border-gray-600 text-blue-600">
                        <span class="text-sm text-gray-700 dark:text-gray-300">
                            Kandidat / Vorschlag
                        </span>
                    </label>
                </div>
            </div>

            {{-- Notiz --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Notiz / Begründung
                    <span class="font-normal text-gray-400">(optional)</span>
                </label>
                <textarea name="notes" rows="3" maxlength="1000"
                          x-model="notes"
                          placeholder="z. B. Ph/MNT, 817 Punkte — oder: für herausragende Leistungen in …"
                          class="w-full text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-md shadow-sm"></textarea>
            </div>

            <div class="flex justify-end space-x-3 pt-2">
                <button type="button" @click="show = false"
                        class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 text-sm rounded-md hover:bg-gray-300">
                    Abbrechen
                </button>
                <button type="submit"
                        class="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">
                    Speichern
                </button>
            </div>
        </form>
    </div>
</div>
@endarchive

<script @cspNonce>
function editAwardModal() {
    return {
        show: false,
        awardId: null,
        studentName: '',
        awardTypeName: '',
        notes: '',
        rank: '',
        isCandidate: false,

        open(detail) {
            this.awardId      = detail.id;
            this.studentName  = detail.student_name;
            this.awardTypeName = detail.award_type_name;
            this.notes        = detail.notes ?? '';
            this.rank         = detail.rank ? String(detail.rank) : '';
            this.isCandidate  = detail.is_candidate ?? false;
            this.show         = true;
        },
    };
}
</script>
