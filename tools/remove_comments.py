utf-8"""
Remove comments from source files across the workspace (safe-ish).
Usage:
  python tools/remove_comments.py --root "c:/CSIT/Projects/WGMS" --exclude vendor Work-logs

This script:
 - Creates a timestamped backup directory under the root (preserves structure)
 - Removes comments from these file types: .html,.htm,.js,.css,.php,.py,.md,.txt
 - Uses Python's tokenizer for .py files (safe removal of COMMENT tokens)
 - Uses a string-aware state machine for C-like files to avoid removing comment-like text inside strings
 - Removes HTML comments (<!-- -->) in HTML/Markdown
 - Writes a report `tools/remove_comments_report.txt` with counts and modified file list

WARNING: Removing comments may remove helpful documentation. You confirmed excluding `vendor/` and `Work-logs/`.
"""
importargparse
importos
importshutil
importdatetime
importio
importsys
importtokenize
importre

C_LIKE_EXT={'.js','.css','.php','.java','.c','.cpp','.h'}
HTML_EXT={'.html','.htm','.md'}
TEXT_EXT={'.txt','.md'}
PY_EXT={'.py'}
TARGET_EXT=C_LIKE_EXT|HTML_EXT|PY_EXT|TEXT_EXT

REPORT_PATH='tools/remove_comments_report.txt'


defbackup_tree(root,backup_dir,exclude_dirs):
    fordirpath,dirnames,filenamesinos.walk(root):

        rel=os.path.relpath(dirpath,root)
ifrel=='.':rel=''
parts=rel.split(os.sep)ifrelelse[]
ifany(pinexclude_dirsforpinparts):
            continue
dest_dir=os.path.join(backup_dir,rel)
os.makedirs(dest_dir,exist_ok=True)
forfinfilenames:
            src=os.path.join(dirpath,f)
dst=os.path.join(dest_dir,f)
shutil.copy2(src,dst)


defremove_html_comments(text):
    new,n=re.subn(r'<!--.*?-->','',text,flags=re.S)
returnnew,n


defstrip_c_like_comments(src):
    out=[]
i=0
n=len(src)
in_squote=in_dquote=in_bquote=False
in_block=False
in_line=False
escaped=False
removed=0
whilei<n:
        ch=src[i]
nch=src[i+1]ifi+1<nelse''
ifin_block:
            ifch=='*'andnch=='/':
                in_block=False
i+=2
removed+=1
continue
else:
                i+=1
continue
ifin_line:
            ifch=='\n':
                in_line=False
out.append(ch)
i+=1
continue
else:
                i+=1
removed+=0
continue
ifnot(in_squoteorin_dquoteorin_bquote):

            ifch=='/'andnch=='*':
                in_block=True
i+=2
continue

ifch=='/'andnch=='/':
                in_line=True
i+=2
continue

ifch=='#':


                in_line=True
i+=1
continue

ifch=='\\':
            out.append(ch)
i+=1
ifi<n:
                out.append(src[i])
i+=1
continue
ifnotin_squoteandch=='"'andnotin_dquoteandnotin_bquote:
            in_dquote=True
out.append(ch)
i+=1
continue
ifin_dquoteandch=='"':
            in_dquote=False
out.append(ch)
i+=1
continue
ifnotin_dquoteandch=='\''andnotin_squoteandnotin_bquote:
            in_squote=True
out.append(ch)
i+=1
continue
ifin_squoteandch=="'":
            in_squote=False
out.append(ch)
i+=1
continue
ifnotin_squoteandnotin_dquoteandch=='`'andnotin_bquote:
            in_bquote=True
out.append(ch)
i+=1
continue
ifin_bquoteandch=='`':
            in_bquote=False
out.append(ch)
i+=1
continue
out.append(ch)
i+=1
return''.join(out)


defprocess_py_file(path):

    try:
        withopen(path,'rb')asf:
            tokens=list(tokenize.tokenize(f.readline))
exceptException:

        returnFalse,0
out=io.BytesIO()
removed=0
fortokintokens:
        iftok.type==tokenize.COMMENT:
            removed+=1
continue
iftok.type==tokenize.ENCODING:
            out.write(tok.string.encode('utf-8'))
continue
out.write(tok.string.encode('utf-8'))
try:
        new=out.getvalue().decode('utf-8')
withopen(path,'w',encoding='utf-8')asf:
            f.write(new)
returnTrue,removed
exceptException:
        returnFalse,0


defprocess_file(path):
    ext=os.path.splitext(path)[1].lower()
try:
        withopen(path,'r',encoding='utf-8',errors='replace')asf:
            data=f.read()
exceptException:
        returnFalse,0
ifextinPY_EXT:
        returnprocess_py_file(path)
changed=False
total_removed=0
new=data
ifextinHTML_EXTorextinTEXT_EXT:
        new,n=remove_html_comments(new)
total_removed+=n
ifextinC_LIKE_EXT:
        processed=strip_c_like_comments(new)
ifprocessed!=new:
            total_removed+=0
changed=True
new=processed
ifnew!=data:
        try:
            withopen(path,'w',encoding='utf-8')asf:
                f.write(new)
returnTrue,total_removed
exceptException:
            returnFalse,0
returnFalse,0


defmain():
    p=argparse.ArgumentParser()
p.add_argument('--root',default='.',help='Workspace root')
p.add_argument('--exclude',nargs='*',default=[],help='Top-level directories to exclude')
args=p.parse_args()
root=os.path.abspath(args.root)
excludes=set(args.exclude)
timestamp=datetime.datetime.now().strftime('%Y%m%d_%H%M%S')
backup_dir=os.path.join(root,'backup_comments_'+timestamp)
os.makedirs(backup_dir,exist_ok=True)
print('Backing up workspace to',backup_dir)
backup_tree(root,backup_dir,excludes|{'backup_comments_'+timestamp})
modified=[]
total_removed=0
fordirpath,dirnames,filenamesinos.walk(root):

        rel=os.path.relpath(dirpath,root)
parts=rel.split(os.sep)ifrel!='.'else[]
ifany(pinexcludesforpinparts):
            continue

if'backup_comments_'indirpath:
            continue
forfninfilenames:
            path=os.path.join(dirpath,fn)
ext=os.path.splitext(fn)[1].lower()
ifextnotinTARGET_EXT:
                continue
ok,removed=process_file(path)
ifok:
                modified.append((os.path.relpath(path,root),removed))
total_removed+=removed

report_lines=[]
report_lines.append(f'Backup dir: {backup_dir}')
report_lines.append(f'Modified files: {len(modified)}')
report_lines.append(f'Total comment blocks removed (approx): {total_removed}')
report_lines.append('')
form,rinmodified:
        report_lines.append(f'{m} — removed: {r}')
withopen(os.path.join(root,REPORT_PATH),'w',encoding='utf-8')asf:
        f.write('\n'.join(report_lines))
print('Done. Report written to',REPORT_PATH)

if__name__=='__main__':
    main()
