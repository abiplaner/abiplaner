<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.written-exam-slots.index') }}" class="text-gray-400 hover:text-gray-600">
                ← Zurück
            </a>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Slot bearbeiten: <span class="text-blue-700">{{ $writtenExamSlot->name }}</span>
            </h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">

                @if($errors->any())
                    <div class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
                        <p class="text-red-700 font-medium text-sm mb-1">Bitte korrigiere folgende Fehler:</p>
                        <ul class="text-red-600 text-sm list-disc list-inside space-y-0.5">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.written-exam-slots.update', $writtenExamSlot) }}">
                    @csrf
                    @method('PUT')
                    @include('admin.written-exam-slots._form', ['slot' => $writtenExamSlot])

                    <div class="flex justify-between items-center mt-8 pt-6 border-t">
                        <div class="text-xs text-gray-400">
                            Angelegt: {{ $writtenExamSlot->created_at->format('d.m.Y H:i') }}
                            | Geändert: {{ $writtenExamSlot->updated_at->format('d.m.Y H:i') }}
                        </div>
                        <div class="flex gap-3">
                            <a href="{{ route('admin.written-exam-slots.index') }}"
                               class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 text-sm">
                                Abbrechen
                            </a>
                            <button type="submit"
                                    class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm font-medium">
                                Änderungen speichern
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
