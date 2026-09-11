@props([
    'name' => 'password',
    'label' => 'Contraseña',
    'autocomplete' => 'current-password',
    'required' => true,
    'autofocus' => false,
    'hint' => null,
])

{{--
    Campo de contraseña con el ojo para verla.

    Escribir una contraseña a ciegas en un móvil es la primera causa de «no me
    deja entrar»: se teclea mal, no se ve, y no hay forma de saber qué se ha
    puesto. Con el ojo se comprueba antes de darle al botón.

    Detalles que importan:

    · El botón es type="button". Dentro de un formulario, un botón sin tipo es
      de envío: darle al ojo mandaría el formulario a medio rellenar.

    · El campo lleva sitio a la derecha (pr-11) para que el texto no se meta
      por debajo del icono al escribir una contraseña larga.

    · tabindex="-1" en el botón: quien va con el teclado pasa del campo al
      botón de entrar, que es lo que quiere hacer. El ojo es para el dedo.

    · Arranca siempre oculta y no se recuerda la preferencia: es una contraseña,
      y lo normal es que haya alguien al lado.
--}}
<div x-data="{ visible: false }">
    <label class="label" for="{{ $name }}">{{ $label }}</label>

    <div class="relative">
        <input id="{{ $name }}" name="{{ $name }}" class="field pr-11"
               :type="visible ? 'text' : 'password'"
               type="password"
               @required($required) @autofocus($autofocus)
               autocomplete="{{ $autocomplete }}" placeholder="••••••••">

        <button type="button" tabindex="-1" @click="visible = ! visible"
                class="absolute inset-y-0 right-0 grid w-11 place-items-center rounded-r-xl text-neutral-400 transition hover:text-neutral-700"
                :aria-label="visible ? 'Ocultar la contraseña' : 'Ver la contraseña'"
                :aria-pressed="visible ? 'true' : 'false'">
            {{-- Ojo abierto cuando está tapada: dice lo que va a PASAR al pulsar --}}
            <svg x-show="! visible" class="size-5" fill="none" stroke="currentColor"
                 stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/>
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
            </svg>

            {{-- Ojo tachado cuando está a la vista --}}
            <svg x-show="visible" x-cloak class="size-5" fill="none" stroke="currentColor"
                 stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 1-4.243-4.243"/>
            </svg>
        </button>
    </div>

    @if ($hint)
        <p class="mt-1.5 text-xs text-neutral-500">{{ $hint }}</p>
    @endif
</div>
