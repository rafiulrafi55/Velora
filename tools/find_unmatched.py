utf-8frompathlibimportPath
p=Path('c:/CSIT/Projects/WGMS/guests.html')
s=p.read_text(encoding='utf-8')
stack=[]
line=1
fori,chinenumerate(s):
    ifch=='\n':
        line+=1
ifch=='{':
        stack.append((i,line))
elifch=='}':
        ifstack:
            stack.pop()
else:
            print('extra closing at',i,line)
ifstack:
    print('unmatched openings:',len(stack))
foridx,lninstack[-10:]:
        print('open at idx',idx,'line',ln)

start=max(0,idx-120)
end=min(len(s),idx+120)
print(s[start:end])
else:
    print('all matched')
