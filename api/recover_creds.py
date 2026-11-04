#!/usr/bin/env python3
import sys, re, os, json, subprocess

def find_node_pid():
    try:
        out = subprocess.check_output(["pgrep", "-f", "dist/index.js|server.js"]).decode().strip()
        pids = [int(p) for p in out.split() if p.isdigit()]
        return pids[0] if pids else None
    except Exception:
        return 1993418

pid = find_node_pid()
if not pid:
    print(json.dumps({"success": False, "error": "Node process not found"}))
    sys.exit(0)

try:
    with open(f"/proc/{pid}/maps") as f:
        maps = f.readlines()
except Exception as e:
    print(json.dumps({"success": False, "error": f"Cannot open maps: {e}"}))
    sys.exit(0)

try:
    mem_fd = os.open(f"/proc/{pid}/mem", os.O_RDONLY)
except Exception as e:
    print(json.dumps({"success": False, "error": f"Cannot open mem: {e}"}))
    sys.exit(0)

matches = []

for line in maps:
    parts = line.strip().split()
    if len(parts) < 2: continue
    addr, perms = parts[0], parts[1]
    if 'r' not in perms or 'w' not in perms: continue
    start_hex, end_hex = addr.split('-')
    start = int(start_hex, 16)
    end = int(end_hex, 16)
    size = end - start
    if size <= 0 or size > 30 * 1024 * 1024: continue

    try:
        chunk = os.pread(mem_fd, size, start)
    except Exception:
        continue

    if b'"registrationId"' in chunk and b'"noiseKey"' in chunk:
        idx = 0
        while True:
            pos = chunk.find(b'{"noiseKey"', idx)
            if pos == -1: break
            sub = chunk[pos:pos+25000]
            brace = 0
            end_idx = -1
            in_str = False
            for i in range(len(sub)):
                c = sub[i:i+1]
                if c == b'"' and (i == 0 or sub[i-1:i] != b'\\'):
                    in_str = not in_str
                elif not in_str:
                    if c == b'{': brace += 1
                    elif c == b'}':
                        brace -= 1
                        if brace == 0:
                            end_idx = i + 1
                            break
            if end_idx > 100:
                cand = sub[:end_idx]
                try:
                    d = json.loads(cand.decode('utf-8', errors='ignore'))
                    if 'noiseKey' in d and 'registrationId' in d:
                        matches.append({
                            'registered': d.get('registered'),
                            'me': d.get('me'),
                            'account': bool(d.get('account')),
                            'signalIdentities': bool(d.get('signalIdentities')),
                            'keys_count': len(d.keys()),
                            'raw_json': cand.decode('utf-8', errors='ignore')
                        })
                except Exception:
                    pass
            idx = pos + 11

os.close(mem_fd)

# Also check for any occurrence of 923318036576 in memory
phone_occurrences = 0
phone_contexts = []
try:
    mem_fd = os.open(f"/proc/{pid}/mem", os.O_RDONLY)
    for line in maps:
        parts = line.strip().split()
        if len(parts) < 2: continue
        addr, perms = parts[0], parts[1]
        if 'r' not in perms or 'w' not in perms: continue
        start_hex, end_hex = addr.split('-')
        start = int(start_hex, 16)
        end = int(end_hex, 16)
        size = end - start
        if size <= 0 or size > 30 * 1024 * 1024: continue
        try:
            chunk = os.pread(mem_fd, size, start)
            if b'923318036576' in chunk:
                phone_occurrences += chunk.count(b'923318036576')
                idx = 0
                while len(phone_contexts) < 10:
                    p = chunk.find(b'923318036576', idx)
                    if p == -1: break
                    c_start = max(0, p - 300)
                    c_end = min(len(chunk), p + 300)
                    phone_contexts.append(chunk[c_start:c_end].decode('latin1', errors='replace'))
                    idx = p + 12
        except Exception:
            continue
    os.close(mem_fd)
except Exception:
    pass

target_chunk = None
try:
    mem_fd = os.open(f"/proc/{pid}/mem", os.O_RDONLY)
    for line in maps:
        parts = line.strip().split()
        if len(parts) < 2: continue
        addr, perms = parts[0], parts[1]
        if 'r' not in perms or 'w' not in perms: continue
        start_hex, end_hex = addr.split('-')
        start = int(start_hex, 16)
        end = int(end_hex, 16)
        size = end - start
        if size <= 0 or size > 30 * 1024 * 1024: continue
        try:
            chunk = os.pread(mem_fd, size, start)
            if b'"registrationId":227' in chunk:
                p = chunk.find(b'"registrationId":227')
                c_start = max(0, p - 8000)
                c_end = min(len(chunk), p + 16000)
                target_chunk = chunk[c_start:c_end]
                break
        except Exception:
            continue
    os.close(mem_fd)
except Exception:
    pass

if target_chunk:
    # Save the raw bytes to /home/dailygol/omnidesk.lalagpt.pk/data/whatsapp-bridge/recovered_raw.bin
    raw_path = "/home/dailygol/omnidesk.lalagpt.pk/data/whatsapp-bridge/recovered_raw.bin"
    with open(raw_path, "wb") as f:
        f.write(target_chunk)

print(json.dumps({
    "success": True,
    "found_target": bool(target_chunk),
    "target_length": len(target_chunk) if target_chunk else 0,
    "preview": target_chunk[:2000].decode('latin1', errors='replace') if target_chunk else None
}, indent=2))
sys.exit(0)
