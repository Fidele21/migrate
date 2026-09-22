{{--
  The filter bar, used by every screen that filters.

  Expects:
    $title      string   heading, e.g. the category name
    $crumb      string   optional line above it
    $filters    array    district, sector, cell, period, from, to
    $cityWide   bool     may this person choose a district
    $district   ?string  their own district if not city-wide
    $resetUrl   string   where Reset goes
    $periodOptions, $periodLabel   when the period control is wanted
    $showPeriod bool     default true
    $actions    string   optional HTML for buttons on the right
--}}

@php
  $showPeriod = $showPeriod ?? true;
  $active = collect($filters)->filter()->count();
@endphp

<form method="get" class="fbar" id="fpanel">
  <div class="fb-id">
    @isset($crumb)<span class="fb-crumb">{{ $crumb }}</span>@endisset
    <span class="fb-name">{{ $title }}</span>
    @isset($periodLabel)
      <span class="fb-scope">{{ $scopeName ?? '' }}@if(($scopeName ?? '') && $periodLabel) &middot; @endif{{ $periodLabel }}</span>
    @endisset
  </div>

  <div class="fb-fields">

    <div class="fb-group">
      <label for="district">District</label>
      <select name="district" id="district" {{ $cityWide ? '' : 'disabled' }}>
        @if($cityWide)
          <option value="">All districts</option>
          @foreach(array_keys(config('kigali')) as $d)
            <option value="{{ $d }}" @selected(($filters['district'] ?? '') === $d)>{{ $d }}</option>
          @endforeach
        @else
          <option>{{ $district }}</option>
        @endif
      </select>
    </div>

    <div class="fb-group">
      <label for="sector">Sector</label>
      <select name="sector" id="sector"><option value="">All sectors</option></select>
    </div>

    <div class="fb-group">
      <label for="cell">Cell</label>
      <select name="cell" id="cell"><option value="">All cells</option></select>
    </div>

    @if($showPeriod)
      <div class="fb-group date-field">
        <label for="date-btn">Period</label>
        <button type="button" class="date-btn" id="date-btn" aria-expanded="false">
          <span>{{ $periodLabel ?? 'All time' }}</span><i>&#9662;</i>
        </button>

        <div class="date-pop" id="date-pop">
          <div class="dp-list">
            <button type="button" class="dp-item {{ blank($filters['period'] ?? null) ? 'on' : '' }}"
                    data-period="">All time</button>
            @foreach(($periodOptions ?? []) as $group => $opts)
              @if($group !== 'Choose dates')
                <div class="dp-group">{{ $group }}</div>
                @foreach($opts as $k => $label)
                  <button type="button" class="dp-item {{ ($filters['period'] ?? '') === $k ? 'on' : '' }}"
                          data-period="{{ $k }}">{{ $label }}</button>
                @endforeach
              @endif
            @endforeach
          </div>
          <div class="dp-custom">
            <div class="dp-group">Custom range</div>
            <div class="dp-row">
              <label for="from">From</label>
              <input type="date" name="from" id="from" max="{{ date('Y-m-d') }}" value="{{ $filters['from'] ?? '' }}">
            </div>
            <div class="dp-row">
              <label for="to">To</label>
              <input type="date" name="to" id="to" max="{{ date('Y-m-d') }}" value="{{ $filters['to'] ?? '' }}">
            </div>
            <button type="button" class="btn btn-primary btn-sm dp-apply" id="date-apply">Apply range</button>
          </div>
        </div>
        <input type="hidden" name="period" id="period" value="{{ $filters['period'] ?? '' }}">
      </div>
    @endif

    <div class="fb-group fb-end">
      <label>&nbsp;</label>
      <a href="{{ $resetUrl }}" class="fb-reset {{ $active ? 'live' : '' }}">
        Reset @if($active)<i>{{ $active }}</i>@endif
      </a>
    </div>

    @isset($actions)
      <div class="fb-group fb-end">
        <label>&nbsp;</label>
        <div class="fb-actions">{!! $actions !!}</div>
      </div>
    @endisset

    <noscript>
      <div class="fb-group fb-end">
        <label>&nbsp;</label>
        <button type="submit" class="btn btn-secondary btn-sm">Apply</button>
      </div>
    </noscript>
  </div>
</form>
