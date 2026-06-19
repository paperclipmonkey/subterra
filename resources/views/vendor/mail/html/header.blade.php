@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ asset('images/subterra-logo.png') }}" class="logo" alt="Subterra Logo">
<span class="brand-name">{{ $slot }}</span>
</a>
</td>
</tr>
