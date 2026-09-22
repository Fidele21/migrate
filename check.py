import re, sys

p = "resources/views/partials/letterhead.blade.php"
s = open(p, encoding="utf-8").read()

print("lines:", len(s.splitlines()))
print("has $legalBasis:", "$legalBasis" in s)
print("has $ccDistrict:", "$ccDistrict" in s)
print()

def walk(seg):
    i, q, d, ql = 0, None, {'(':0,'[':0,'{':0}, None
    pair = {')':'(', ']':'[', '}':'{'}
    while i < len(seg):
        ch = seg[i]
        if q:
            if ch == '\\': i += 2; continue
            if ch == q: q = None
            i += 1; continue
        if ch in "'\"":
            q, ql = ch, seg[:i].count("\n") + 1
            i += 1; continue
        if seg[i:i+2] == '/*':
            j = seg.find('*/', i); i = (j+2) if j != -1 else len(seg); continue
        if seg[i:i+2] == '//':
            j = seg.find('\n', i); i = (j+1) if j != -1 else len(seg); continue
        if ch in '([{': d[ch] += 1
        elif ch in ')]}': d[pair[ch]] -= 1
        i += 1
    return {k: v for k, v in d.items() if v}, q, ql

for n, m in enumerate(re.finditer(r'@php(.*?)@endphp', s, re.S), 1):
    base = s[:m.start()].count("\n") + 1
    bad, q, ql = walk(m.group(1))
    if bad or q:
        print(f"@php block {n} (line {base}): ", end="")
        if q: print(f"UNTERMINATED {q} at block line {ql} → file line {base + ql}")
        else: print("unbalanced", bad)
    else:
        print(f"@php block {n} (line {base}): balanced")

print()
body = re.sub(r'\{\{--.*?--\}\}', '', s, flags=re.S)
found = False
for m in re.finditer(r'\{\{(.*?)\}\}|\{!!(.*?)!!\}', body, re.S):
    e = (m.group(1) or m.group(2))
    if '\n' in e.strip():
        ln = body[:m.start()].count("\n") + 1
        print(f"MULTI-LINE expression at line {ln}: {e.strip()[:90]}")
        found = True
if not found:
    print("inline expressions: all single-line")

print()
for o, c in [("@if(","@endif"), ("@foreach(","@endforeach"), ("@php","@endphp"),
             ("@unless(","@endunless"), ("@forelse(","@endforelse")]:
    a, b = s.count(o), s.count(c)
    if a or b:
        print(f"{o:12} {a:3}  {c:14} {b:3}  {'OK' if a==b else '<<< MISMATCH'}")