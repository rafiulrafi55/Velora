from pathlib import Path
p = Path('c:/CSIT/Projects/WGMS/guests.html')
s = p.read_text(encoding='utf-8')
stack = []
line=1
for i,ch in enumerate(s):
    if ch=='\n':
        line+=1
    if ch=='{':
        stack.append((i,line))
    elif ch=='}':
        if stack:
            stack.pop()
        else:
            print('extra closing at',i,line)
if stack:
    print('unmatched openings:', len(stack))
    for idx,ln in stack[-10:]:
        print('open at idx',idx,'line',ln)
        # print context
        start = max(0, idx-120)
        end = min(len(s), idx+120)
        print(s[start:end])
else:
    print('all matched')
