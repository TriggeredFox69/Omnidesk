#!/usr/bin/env python3
import json, re

raw_path = "/home/dailygol/omnidesk.lalagpt.pk/data/whatsapp-bridge/recovered_raw.bin"
with open(raw_path, "rb") as f:
    data = f.read()

# Find all JSON-like strings
pos = 0
found_objects = []

for m in re.finditer(b'\{[^{}]*"registrationId"[^{}]*\}', data):
    try:
        found_objects.append(json.loads(m.group(0).decode('utf-8', errors='ignore')))
    except Exception:
        pass

# Also look for { ... } enclosing "registrationId":227
idx = data.find(b'"registrationId":227')
extracted = []
if idx != -1:
    # Scan backward for opening {
    for start in range(max(0, idx - 2000), idx):
        if data[start:start+1] == b'{':
            # Scan forward for matching closing }
            brace = 0
            in_str = False
            for end in range(start, min(len(data), idx + 4000)):
                c = data[end:end+1]
                if c == b'"' and (end == 0 or data[end-1:end] != b'\\'):
                    in_str = not in_str
                elif not in_str:
                    if c == b'{': brace += 1
                    elif c == b'}':
                        brace -= 1
                        if brace == 0:
                            sub = data[start:end+1]
                            try:
                                d = json.loads(sub.decode('utf-8', errors='ignore'))
                                if 'registrationId' in d:
                                    extracted.append(d)
                            except Exception:
                                pass
                            break

print(json.dumps({
    "success": True,
    "found_objects": found_objects,
    "extracted_count": len(extracted),
    "extracted": extracted
}, indent=2))
