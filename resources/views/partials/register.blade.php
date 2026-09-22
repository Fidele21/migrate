{{-- Inspection register, in the format used by the inspection unit --}}
<section id="records">
  <h3>Inspection Register</h3>
  <div class="desc">
    One row per premises. <strong>Inspections</strong> shows how many times the premises has
    been visited; <strong>Compliance</strong> and <strong>Deliberation</strong> reflect the most
    recent visit.
  </div>

  <div class="tbl-wrap">
    <table class="register">
      <thead>
        <tr>
          <th style="width:38px">#</th>
          <th>UPI</th>
          <th>Zoning</th>
          <th>Owner</th>
          <th>{{ $type['name'] }} Name</th>
          <th>District</th>
          <th>Date of Inspection</th>
          <th style="text-align:right">Compliance</th>
          <th style="text-align:center">Inspections</th>
          <th>Deliberation</th>
          <th style="text-align:right">Report</th>
        </tr>
      </thead>
      <tbody>
        @forelse($register as $n => $r)
          @php [$verdict, $tone] = \App\Services\LegacyStats::deliberation((float) $r->compliance); @endphp
          <tr>
            <td style="color:var(--muted)">{{ $n + 1 }}</td>
            <td style="font-variant-numeric:tabular-nums;font-size:12px">{{ $r->upi ?: '—' }}</td>
            <td style="font-size:12.5px">{{ $r->zoning ?: '—' }}</td>
            <td>{{ $r->owner ?: '—' }}</td>
            <td>
              <a href="{{ route('entity.show', $r->entity_id) }}" style="font-weight:700;color:var(--blue);text-decoration:none">{{ $r->name }}</a>
              @if($r->sector)<br><span style="color:var(--muted);font-size:11.5px">{{ $r->sector }}</span>@endif
            </td>
            <td>{{ $r->district ?: '—' }}</td>
            <td style="white-space:nowrap;font-size:12.5px">{{ $r->last_inspection }}</td>
            <td class="num"><span class="pill {{ \App\Services\LegacyStats::band((float) $r->compliance) }}">{{ $r->compliance }}%</span></td>
            <td style="text-align:center">
              <span class="count-chip {{ $r->inspection_count > 1 ? 'multi' : '' }}">{{ $r->inspection_count }}</span>
            </td>
            <td><span class="pill {{ $tone }}">{{ $verdict }}</span></td>
            <td class="num">
              <a href="{{ route('entity.show', $r->entity_id) }}" class="btn btn-ghost btn-sm">View Report</a>
            </td>
          </tr>
        @empty
          <tr><td colspan="11" style="text-align:center;color:var(--muted);padding:30px">
            No premises match these filters.
          </td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</section>

<style>
  table.register td{vertical-align:middle}
  .count-chip{display:inline-flex;align-items:center;justify-content:center;min-width:26px;height:24px;
    padding:0 7px;border-radius:12px;background:#EDF2FC;color:var(--blue);
    font-weight:800;font-size:12px;font-variant-numeric:tabular-nums}
  .count-chip.multi{background:#FFF4D6;color:#8A6D00}
</style>
