<div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(120px,1fr));gap:12px;">
  @forelse($fotos as $f)
    <div style="text-align:center;">
      <img
        src="{{ route('media.local', $f->id) }}"
        alt="foto"
        style="width:120px;height:120px;object-fit:cover;border-radius:10px;border:1px solid #e5e7eb;cursor:pointer;transition:transform 0.2s ease,box-shadow 0.2s ease;"
        onclick="window.open('{{ route('media.local', $f->id) }}', '_blank', 'width=800,height=600,scrollbars=yes,resizable=yes')"
        onmouseover="this.style.transform='scale(1.05)';this.style.boxShadow='0 4px 12px rgba(0,0,0,0.15)'"
        onmouseout="this.style.transform='scale(1)';this.style.boxShadow='none'"
        title="Clic para ver en tamaño completo"
      >
      @if($f->author_name)
        <div style="font-size:11px;color:#666;margin-top:4px;">
          © <a href="{{ $f->author_uri }}" target="_blank" rel="noopener" style="color:#666;">
            {{ $f->author_name }}
          </a>
        </div>
      @endif
    </div>
  @empty
    <div>No hay fotos descargadas para este candidato.</div>
  @endforelse
</div>
