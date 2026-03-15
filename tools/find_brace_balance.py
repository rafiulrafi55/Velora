from pathlib import Path
p = Path('c:/CSIT/Projects/WGMS/guests.html')
s = p.read_text(encoding='utf-8')
bal=0
line=1
first_negative=None
positions=[]
for i,ch in enumerate(s):
    if ch=='\n':
        line+=1
    if ch=='{':
        bal+=1
    elif ch=='}':
        bal-=1
    if bal<0 and first_negative is None:
        first_negative=(i,line)
    positions.append((i,ch,bal,line))
print('final balance',bal)
if first_negative:
    print('first negative at idx,line',first_negative)
# find last index where balance was highest
max_bal = max(p[2] for p in positions)
print('max balance', max_bal)
# find last position where bal==max_bal
last_max = [p for p in positions if p[2]==max_bal][-1]
print('last max position', last_max[0], 'line', last_max[3])
# show context around that line
lines = s.splitlines()
ln = last_max[3]
start = max(1, ln-8)
end = min(len(lines), ln+8)
print('\nContext around last high-balance line:')
for i in range(start, end+1):
    print(f"{i:04}: {lines[i-1]}")
print('\n---')
