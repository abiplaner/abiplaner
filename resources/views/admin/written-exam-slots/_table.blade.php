@if($slotGroup->isEmpty())
    <div class="px-6 py-8 text-center text-gray-500 text-sm">
        Keine Slots vorhanden.
    </div>
@else
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-12">Nr.</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Uhrzeit</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dauer</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fächer</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aktionen</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-100">
                @foreach($slotGroup as $slot)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-gray-400 font-mono text-xs">{{ $slot->sort_order }}</td>
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $slot->name }}</td>
                        <td class="px-4 py-3 text-gray-700 font-mono whitespace-nowrap">
                            {{ \Carbon\Carbon::parse($slot->start_time)->format('H:i') }}
                            –
                            {{ \Carbon\Carbon::parse($slot->end_time)->format('H:i') }}
                        </td>
                        <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                            {{ $slot->getDurationMinutes() }} Min.
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1">
                                @foreach($slot->subjects as $subject)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium text-white"
                                          style="background-color: {{ $subject->color }}">
                                        {{ $subject->code }}
                                    </span>
                                @endforeach
                                @if($slot->subjects->isEmpty())
                                    <span class="text-xs text-red-500 italic">Kein Fach zugeordnet!</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap space-x-3">
                            <a href="{{ route('admin.written-exam-slots.edit', $slot) }}"
                               class="text-blue-600 hover:text-blue-800 text-xs font-medium">
                                Bearbeiten
                            </a>
                            <form action="{{ route('admin.written-exam-slots.destroy', $slot) }}"
                                  method="POST" class="inline"
                                  data-confirm="Slot „{{ $slot->name }}“ wirklich löschen?&#10;Achtung: Geht nur wenn keine Jahrestermine mehr daran hängen.">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-medium">
                                    Löschen
                                </button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
