{{-- Row actions of a party: edit (opens the modal via recordForm), stop / reactivate. Used by the table and the mobile cards. --}}
<div class="inline-flex items-center gap-1">
    <x-ui.button size="sm" variant="ghost" type="button"
                 @click="edit({{ Js::from($party->only(['id', 'name', 'phone']) + ['type' => $party->type->value]) }})">تعديل</x-ui.button>
    @if ($party->is_active)
        <x-ui.confirm :title="'إيقاف «'.$party->name.'»؟'" :action="route('parties.toggle', $party)" method="PATCH" confirm-label="إيقاف" cancel-label="تراجع">
            <x-slot:trigger>
                <x-ui.button size="sm" variant="ghost" type="button" class="!text-danger">إيقاف</x-ui.button>
            </x-slot:trigger>
            ما رح يظهر بالحركات الجديدة. حركاته القديمة والتقارير ما بتتأثر، وفيك تعيد تفعيله بأي وقت.
        </x-ui.confirm>
    @else
        <form method="POST" action="{{ route('parties.toggle', $party) }}">
            @csrf @method('PATCH')
            <x-ui.button size="sm" variant="ghost">تفعيل</x-ui.button>
        </form>
    @endif
</div>
