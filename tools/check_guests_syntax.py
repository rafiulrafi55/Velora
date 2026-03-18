utf-8frompathlibimportPath
p=Path('c:/CSIT/Projects/WGMS/guests.html')
s=p.read_text(encoding='utf-8')
counts={"{":s.count('{'),"}":s.count('}'),"(":s.count('('),")":s.count(')'),"[":s.count('['),"]":s.count(']'),"`":s.count('`'),"'":s.count("'"),'"':s.count('"')}
print('counts:',counts)
lines=s.splitlines()
print('total lines:',len(lines))
print('\nlast 30 lines with numbers:\n')
fori,linenumerate(lines[-30:],start=len(lines)-29):
    print(f"{i:04}: {l}")
print('\n---file tail (last 400 chars)---\n')
print(s[-400:])
