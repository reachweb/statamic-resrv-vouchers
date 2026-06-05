@component('mail::message')

{{ __('Thank you for attending!') }}

@component('mail::panel')
@if ($reservation && $reservation->customer)
{{ __('Guest') }}: **{{ trim(($reservation->customer->data['first_name'] ?? '').' '.($reservation->customer->data['last_name'] ?? '')) }}**<br>
@endif
@if ($reservation)
{{ __('Booking reference') }}: **{{ $reservation->reference }}**<br>
{{ __('Dates') }}: **{{ $reservation->date_start?->format('d-m-Y') }} – {{ $reservation->date_end?->format('d-m-Y') }}**
@endif
@endcomponent

{{ __('We hope you enjoyed your stay. We look forward to seeing you again.') }}

{{ __('Thank you') }},<br>
{{ config('app.name') }}
@endcomponent
