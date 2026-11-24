@props(['size' => 'sidebar'])
@php($variant = in_array($size, ['sidebar', 'auth', 'hero'], true) ? $size : 'sidebar')
<img src="{{ asset('images/vvthoa-logo.png') }}" alt="VVTHOA Water System Services" class="organization-logo organization-logo--{{ $variant }}" width="64" height="64">
