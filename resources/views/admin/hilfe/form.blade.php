<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $neu ? 'Neues Hilfekapitel' : 'Kapitel bearbeiten: ' . $kapitel->titel }}
            </h2>
            <div class="flex gap-2">
                @unless($neu)
                    <a href="{{ route('hilfe.show', $kapitel->slug) }}"
                       class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                        In der Hilfe ansehen
                    </a>
                    <a href="{{ route('admin.hilfe.versionen', $kapitel) }}"
                       class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                        Versionen
                    </a>
                @endunless
                <a href="{{ route('admin.hilfe.index') }}"
                   class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700">
                    ← Übersicht
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                    {{ session('success') }}
                </div>
            @endif

            <form method="POST"
                  action="{{ $neu ? route('admin.hilfe.store') : route('admin.hilfe.update', $kapitel) }}"
                  x-data="{
                      markdown: @js(old('markdown', $kapitel->markdown ?? '')),
                      vorschau: @js($vorschau ?? ''),
                      laeuft: false,
                      aktualisieren() {
                          this.laeuft = true;
                          fetch(@js(route('admin.hilfe.vorschau')), {
                              method: 'POST',
                              headers: {
                                  'Content-Type': 'application/json',
                                  'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                              },
                              body: JSON.stringify({ markdown: this.markdown }),
                          })
                          .then(a => a.json())
                          .then(d => { this.vorschau = d.html; this.laeuft = false; })
                          .catch(() => { this.laeuft = false; });
                      }
                  }">
                @csrf
                @unless($neu) @method('PUT') @endunless

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 space-y-4">
                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Titel *</label>
                            <input type="text" name="titel" required maxlength="200"
                                   value="{{ old('titel', $kapitel->titel) }}"
                                   class="mt-1 w-full border-gray-300 rounded-md shadow-sm text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Schlüssel (Adresse) *</label>
                            <input type="text" name="slug" required maxlength="100"
                                   value="{{ old('slug', $kapitel->slug) }}"
                                   class="mt-1 w-full border-gray-300 rounded-md shadow-sm text-sm font-mono">
                            <p class="text-xs text-gray-500 mt-1">
                                Nur Kleinbuchstaben, Ziffern und Bindestriche — er steht in der Adresse
                                <code>/hilfe/…</code>. Ändern macht bestehende Verweise ungültig.
                            </p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Gruppe *</label>
                            <input type="text" name="gruppe" required maxlength="100" list="hilfe-gruppen"
                                   value="{{ old('gruppe', $kapitel->gruppe) }}"
                                   class="mt-1 w-full border-gray-300 rounded-md shadow-sm text-sm">
                            <datalist id="hilfe-gruppen">
                                @foreach(\App\Models\HilfeKapitel::query()->distinct()->pluck('gruppe') as $g)
                                    <option value="{{ $g }}"></option>
                                @endforeach
                            </datalist>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Reihenfolge *</label>
                            <input type="number" name="reihenfolge" required min="0" max="9999"
                                   value="{{ old('reihenfolge', $kapitel->reihenfolge) }}"
                                   class="mt-1 w-full border-gray-300 rounded-md shadow-sm text-sm">
                            <p class="text-xs text-gray-500 mt-1">
                                Bestimmt beides: die Folge der Kapitel und — über das kleinste Kapitel
                                einer Gruppe — die Folge der Gruppen.
                            </p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Routenpräfixe</label>
                        <input type="text" name="routen" maxlength="2000"
                               value="{{ old('routen', $kapitel->routen) }}"
                               placeholder="planning, kolloquium.forms"
                               class="mt-1 w-full border-gray-300 rounded-md shadow-sm text-sm font-mono">
                        <p class="text-xs text-gray-500 mt-1">
                            Mit Komma trennen. Das Kapitel erscheint am „?" auf jeder Seite, deren
                            Routenname so heißt oder mit dem Präfix und einem Punkt beginnt.
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Stichworte</label>
                        <input type="text" name="stichworte" maxlength="2000"
                               value="{{ old('stichworte', $kapitel->stichworte) }}"
                               class="mt-1 w-full border-gray-300 rounded-md shadow-sm text-sm">
                        <p class="text-xs text-gray-500 mt-1">
                            Mit Komma trennen. Zusätzliche Begriffe für die Suche — der Fließtext wird
                            ohnehin durchsucht.
                        </p>
                    </div>
                </div>

                <div class="grid lg:grid-cols-2 gap-6 mt-6">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="flex justify-between items-center mb-2">
                            <label class="block text-sm font-medium text-gray-700">Text (Markdown) *</label>
                            <button type="button" @click="aktualisieren()"
                                    class="text-xs px-3 py-1 border border-gray-300 rounded hover:bg-gray-50">
                                <span x-show="!laeuft">Vorschau aktualisieren</span>
                                <span x-show="laeuft">…</span>
                            </button>
                        </div>
                        <textarea name="markdown" required rows="30" x-model="markdown"
                                  class="w-full border-gray-300 rounded-md shadow-sm text-sm font-mono"></textarea>
                        <p class="text-xs text-gray-500 mt-1">
                            Überschriften ab <code>##</code>, Listen mit <code>-</code>, Fettdruck mit
                            <code>**…**</code>, Hinweiskästen mit <code>&gt;</code>. HTML wird entfernt.
                        </p>
                    </div>

                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <p class="text-sm font-medium text-gray-700 mb-3">Vorschau</p>
                        <div class="hilfe-inhalt" x-html="vorschau"></div>
                    </div>
                </div>

                <div class="mt-6 flex flex-wrap justify-between items-center gap-3">
                    <button type="submit"
                            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        {{ $neu ? 'Kapitel anlegen' : 'Speichern' }}
                    </button>

                    @unless($neu)
                        <span class="text-xs text-gray-500">
                            Die bisherige Fassung wird beim Speichern als Version aufbewahrt.
                        </span>
                    @endunless
                </div>
            </form>

            @unless($neu)
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-sm font-semibold text-gray-700">Kapitel löschen</h3>
                    <p class="text-sm text-gray-600 mt-1">
                        Entfernt das Kapitel samt seiner Versionen. Steht es in der Grundfassung, lässt es
                        sich mit <code>php artisan hilfe:einspielen</code> zurückholen — sonst ist es weg.
                    </p>
                    <form method="POST" action="{{ route('admin.hilfe.destroy', $kapitel) }}" class="mt-3"
                          data-confirm="Kapitel „{{ $kapitel->titel }}“ wirklich löschen? Die Versionen fallen mit.">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="px-4 py-2 border border-red-300 text-red-700 rounded-md hover:bg-red-50">
                            Löschen
                        </button>
                    </form>
                </div>
            @endunless

        </div>
    </div>
</x-app-layout>
