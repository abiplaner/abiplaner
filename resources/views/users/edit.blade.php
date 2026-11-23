<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Benutzer bearbeiten') }}: {{ $user->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('admin.users.update', $user) }}">
                        @csrf
                        @method('PUT')

                        <div class="grid grid-cols-2 gap-4">
                            <!-- Name -->
                            <div class="col-span-2">
                                <x-input-label for="name" :value="__('Name')" />
                                <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" 
                                    :value="old('name', $user->name)" required autofocus />
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>

                            <!-- E-Mail -->
                            <div class="col-span-2">
                                <x-input-label for="email" :value="__('E-Mail')" />
                                <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" 
                                    :value="old('email', $user->email)" required />
                                <x-input-error :messages="$errors->get('email')" class="mt-2" />
                            </div>

                            <!-- Passwort (optional) -->
                            <div>
                                <x-input-label for="password" :value="__('Neues Passwort (optional)')" />
                                <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" />
                                <p class="mt-1 text-xs text-gray-500">Leer lassen, um Passwort nicht zu ändern</p>
                                <x-input-error :messages="$errors->get('password')" class="mt-2" />
                            </div>

                            <!-- Passwort bestätigen -->
                            <div>
                                <x-input-label for="password_confirmation" :value="__('Passwort bestätigen')" />
                                <x-text-input id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" />
                            </div>

                            <!-- Schule -->
                            <div class="col-span-2">
                                <x-input-label for="school_id" :value="__('Schule')" />
                                <select id="school_id" name="school_id" :required="role !== 'super_admin'"
                                    class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    <option value="">Schule wählen...</option>
                                    @foreach($schools as $school)
                                        <option value="{{ $school->school_number }}" {{ old('school_id', $user->school_id) == $school->school_number ? 'selected' : '' }}>
                                            {{ $school->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('school_id')" class="mt-2" />
                            </div>

                            <!-- Rolle -->
                            <div class="col-span-2">
                                <x-input-label for="role" :value="__('Rolle')" />
                                <select id="role" name="role" required
                                    class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    <option value="planner" {{ old('role', $user->role) === 'planner' ? 'selected' : '' }}>
                                        Planer
                                    </option>
                                    <option value="super_admin" {{ old('role', $user->role) === 'super_admin' ? 'selected' : '' }}>
                                        Super Admin
                                    </option>
                                </select>
                                <x-input-error :messages="$errors->get('role')" class="mt-2" />
                            </div>

                            <!-- Aktiv -->
                            <div class="col-span-2">
                                <label class="flex items-center">
                                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $user->is_active) ? 'checked' : '' }}
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                    <span class="ml-2 text-sm text-gray-600">{{ __('Benutzer ist aktiv') }}</span>
                                </label>
                            </div>
                        </div>

                        <div class="flex items-center justify-between mt-6">
                            <a href="{{ route('admin.users.index') }}" 
                                class="text-gray-600 hover:text-gray-900">
                                Abbrechen
                            </a>
                            <div class="flex gap-4">
                                @if(auth()->id() !== $user->id)
                                    <button type="button" data-confirm="Wirklich löschen?" data-submit="deleteForm" 
                                        class="text-red-600 hover:text-red-900">
                                        Löschen
                                    </button>
                                @endif
                                <x-primary-button>
                                    {{ __('Änderungen speichern') }}
                                </x-primary-button>
                            </div>
                        </div>
                    </form>

                    @if(auth()->id() !== $user->id)
                        <!-- Delete Form -->
                        <form id="deleteForm" method="POST" action="{{ route('admin.users.destroy', $user) }}" class="hidden">
                            @csrf
                            @method('DELETE')
                        </form>
                    @endif
                </div>
            </div>

            <!-- Zwei-Faktor-Authentifizierung (Admin-Verwaltung) -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mt-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900">
                        {{ __('Zwei-Faktor-Authentifizierung') }}
                    </h3>

                    @if($user->hasTwoFactorEnabled())
                        <p class="mt-1 text-sm text-gray-600">
                            <span class="inline-block w-2 h-2 rounded-full bg-green-500 align-middle"></span>
                            {{ __('Aktiv') }} &middot;
                            {{ trans_choice(':count vertrauenswürdiges Gerät|:count vertrauenswürdige Geräte', $user->trustedDevices()->count(), ['count' => $user->trustedDevices()->count()]) }}
                        </p>

                        <div class="flex flex-wrap gap-4 mt-4">
                            <form method="POST" action="{{ route('admin.users.two-factor.devices.reset', $user) }}"
                                  data-confirm="{{ __('Alle vertrauenswürdigen Geräte dieses Nutzers zurücksetzen?') }}">
                                @csrf
                                <button type="submit" class="text-sm text-gray-700 underline hover:text-gray-900">
                                    {{ __('Vertrauenswürdige Geräte zurücksetzen') }}
                                </button>
                            </form>

                            <form method="POST" action="{{ route('admin.users.two-factor.reset', $user) }}"
                                  data-confirm="{{ __('2FA dieses Nutzers vollständig zurücksetzen? Der Nutzer muss sie danach neu einrichten.') }}">
                                @csrf
                                <button type="submit" class="text-sm text-red-600 underline hover:text-red-900">
                                    {{ __('2FA komplett zurücksetzen') }}
                                </button>
                            </form>
                        </div>
                        <p class="mt-3 text-xs text-gray-500">
                            {{ __('Hinweis: Beim vollständigen Zurücksetzen muss der Nutzer die 2FA selbst neu einrichten (es gibt keine Wiederherstellungscodes).') }}
                        </p>
                    @else
                        <p class="mt-1 text-sm text-gray-600">
                            <span class="inline-block w-2 h-2 rounded-full bg-gray-400 align-middle"></span>
                            {{ __('Nicht eingerichtet') }}
                        </p>
                        <p class="mt-2 text-xs text-gray-500">
                            {{ __('Die 2FA kann nur vom Nutzer selbst im eigenen Profil aktiviert werden.') }}
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
