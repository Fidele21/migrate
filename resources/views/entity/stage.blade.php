@extends('layouts.app')
@section('title', 'Choose the stage')

@section('content')

<div class="page-head">
  <div>
    <div class="crumb">{{ $type['group'] }} &middot; {{ $type['name'] }}</div>
    <h2>Which stage are you inspecting?</h2>
    <p>
      Substructure work is finished and buried before superstructure begins,
      so the two are assessed separately and against different checklists.
    </p>
  </div>
  <div class="head-actions">
    <a href="{{ route('type.show', $type['code']) }}" class="btn btn-ghost">Cancel</a>
  </div>
</div>

<div class="stage-grid">
  @foreach($stages as $key => $s)
    <a href="{{ route('entity.add', ['code' => $type['code'], 'stage' => $key]) }}" class="stage-card">
      <div class="sc-top">
        <span class="sc-tag">{{ $s['label'] }}</span>
        @if($s['template'])
          <span class="sc-meta">{{ $s['items'] }} items &middot; v{{ $s['template']->version }}</span>
        @endif
      </div>

      <h3>{{ $s['heading'] }}</h3>
      <p>{{ $s['blurb'] }}</p>

      @if($s['template'])
        <div class="sc-sections">
          @foreach($s['template']->sections as $sec)
            <span>{{ $sec->title }}</span>
          @endforeach
        </div>
        <span class="sc-go">Continue &rsaquo;</span>
      @else
        <div class="sc-none">No published checklist for this stage.</div>
      @endif
    </a>
  @endforeach
</div>

@endsection

@push('styles')
<style>
  .stage-grid{display:grid;gap:20px;grid-template-columns:1fr}
  @media(min-width:820px){.stage-grid{grid-template-columns:1fr 1fr}}

  .stage-card{position:relative;display:block;background:var(--white);padding:26px 28px 56px;
              box-shadow:var(--shadow-sm);border-top:4px solid var(--primary);
              text-decoration:none;transition:all .2s}
  .stage-card:hover{box-shadow:var(--shadow);transform:translateY(-2px)}

  .sc-top{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:14px}
  .sc-tag{font-family:var(--f-head);font-size:9.5px;font-weight:700;letter-spacing:1.2px;
          text-transform:uppercase;color:#fff;background:var(--primary);padding:4px 10px}
  .sc-meta{font-family:var(--f-head);font-size:10.5px;color:var(--tertiary);font-weight:600}

  .stage-card h3{font-family:var(--f-head);font-size:20px;font-weight:700;color:var(--ink);
                 margin-bottom:8px}
  .stage-card p{font-size:13.5px;color:var(--body-text);line-height:1.65;margin-bottom:16px}

  .sc-sections{display:flex;flex-wrap:wrap;gap:6px;padding-top:14px;border-top:1px solid var(--border)}
  .sc-sections span{font-family:var(--f-head);font-size:10.5px;font-weight:600;
                    color:var(--body-text);background:var(--canvas);padding:4px 9px}

  .sc-go{position:absolute;right:26px;bottom:20px;font-family:var(--f-head);font-size:11px;
         font-weight:600;letter-spacing:.8px;text-transform:uppercase;color:var(--primary)}
  .stage-card:hover .sc-go{color:var(--primary-dark)}

  .sc-none{font-size:12.5px;color:var(--danger);font-style:italic;padding-top:14px;
           border-top:1px solid var(--border)}
</style>
@endpush