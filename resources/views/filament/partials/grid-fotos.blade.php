<div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(120px,1fr));gap:12px;">
  @forelse($fotos as $f)
    <div style="text-align:center;">
      <img
        src="{{ route('media.local', $f->id) }}"
        alt="foto"
        style="width:120px;height:120px;object-fit:cover;border-radius:10px;border:1px solid #e5e7eb;"
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
