@props(['url'])
@php
    $logoPath = public_path('images/logo.jpeg');
    $logoSrc = file_exists($logoPath)
        ? 'data:image/jpeg;base64,' . base64_encode(file_get_contents($logoPath))
        : config('app.url') . '/images/logo.jpeg';
@endphp
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ $logoSrc }}" class="logo" alt="Simplon Burkina Faso">
</a>
</td>
</tr>
