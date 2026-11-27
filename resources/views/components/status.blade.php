@props(['s'])
<span class="pill s-{{ $s }}">{{ __(ucfirst($s === 'attended' ? 'Patient attended' : $s)) }}</span>
