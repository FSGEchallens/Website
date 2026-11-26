@if (session('message'))
    <div class="alerte alerte--succes" role="status">{{ session('message') }}</div>
@endif
