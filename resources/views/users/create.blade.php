<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Benutzer hinzufügen') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('admin.users.store') }}">
                        @csrf
                        
                        @if(request('school_id'))
                            <input type="hidden" name="from_school" value="1">
                        @endif

                        <div class="grid grid-cols-2 gap-4">
                            <!-- Name -->
                            <div class="col-span-2">
                                <x-input-label for="name" :value="__('Name')" />
                                <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" 
                                    :value="old('name')" required autofocus />
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>

                            <!-- E-Mail -->
                            <div class="col-span-2">
                                <x-input-label for="email" :value="__('E-Mail')" />
                                <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" 
                                    :value="old('email')" required />
                                <x-input-error :messages="$errors->get('email')" class="mt-2" />
                            </div>

                            <!-- Passwort -->
                            <div>
                                <x-input-label for="password" :value="__('Passwort')" />
                                <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required />
                                <x-input-error :messages="$errors->get('password')" class="mt-2" />
                            </div>

                            <!-- Passwort bestätigen -->
                            <div>
                                <x-input-label for="password_confirmation" :value="__('Passwort bestätigen')" />
                                <x-text-input id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" required />
                            </div>

                            @include('users.partials.rolle', ['aktuell' => 'planner'])

                            <!-- Aktiv -->
                            <div class="col-span-2">
                                <label class="flex items-center">
                                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                    <span class="ml-2 text-sm text-gray-600">{{ __('Benutzer ist aktiv') }}</span>
                                </label>
                            </div>
                        </div>

                        <div class="flex items-center justify-end mt-6">
                            <a href="{{ route('admin.users.index') }}" 
                                class="text-gray-600 hover:text-gray-900 mr-4">
                                Abbrechen
                            </a>
                            <x-primary-button>
                                @if(request('school_id'))
                                    {{ __('Planer erstellen') }}
                                @else
                                    {{ __('Benutzer erstellen') }}
                                @endif
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
