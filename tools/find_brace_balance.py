utf-8frompathlibimportPath
p=Path('c:/CSIT/Projects/WGMS/guests.html')
s=p.read_text(encoding='utf-8')
bal=0
line=1
first_negative=None
positions=[]
fori,chinenumerate(s):
    ifch=='\n':
        line+=1
ifch=='{':
        bal+=1
elifch=='}':
        bal-=1
ifbal<0andfirst_negativeisNone:
        first_negative=(i,line)
positions.append((i,ch,bal,line))
print('final balance',bal)
iffirst_negative:
    print('first negative at idx,line',first_negative)

max_bal=max(p[2]forpinpositions)
print('max balance',max_bal)

last_max=[pforpinpositionsifp[2]==max_bal][-1]
print('last max position',last_max[0],'line',last_max[3])

lines=s.splitlines()
ln=last_max[3]
start=max(1,ln-8)
end=min(len(lines),ln+8)
print('\nContext around last high-balance line:')
foriinrange(start,end+1):
    print(f"{i:04}: {lines[i-1]}")
print('\n---')
