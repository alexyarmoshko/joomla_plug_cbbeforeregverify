"""Parallel-burst test of the gateway's attempt limit, send limits and cooldown (F2).

See README.md in this directory for what it changes on the site and how to run it.
"""

import argparse, concurrent.futures, datetime, hashlib, html, json, pathlib, re, subprocess, threading, time, urllib.parse

cli = argparse.ArgumentParser(description=__doc__.splitlines()[0])
cli.add_argument('--site', required=True, help='DDEV project directory of the test site')
cli.add_argument('--gateway-url', required=True, help="gateway URL up to and including 'func=', e.g. https://host:8443/index.php/<menu>/pluginclass/cbbeforeregverify?func=")
cli.add_argument('--db-prefix', required=True, help='Joomla table prefix, e.g. jos_')
cli.add_argument('--out', required=True, help='empty directory for evidence; must be outside this repository')
opts = cli.parse_args()

REPO = pathlib.Path(__file__).resolve().parents[2]
ROOT = pathlib.Path(opts.out).resolve()
if ROOT == REPO or REPO in ROOT.parents:
    cli.error('--out must be outside the repository')
ROOT.mkdir(parents=True, exist_ok=True)
SITE = pathlib.Path(opts.site).resolve()
URL = opts.gateway_url
PREFIX = opts.db_prefix
DEFAULT = dict(rl_ip_enabled='1', rl_ip_short_window_min='15', rl_ip_short_max='5', rl_ip_day_window_hours='24', rl_ip_day_max='25', rl_email_enabled='1', rl_email_short_window_min='30', rl_email_short_max='3', rl_email_day_window_hours='24', rl_email_day_max='10', rl_resend_enabled='1', rl_resend_cooldown_sec='60', rl_attempts_enabled='1', rl_attempts_max='8', verification_ttl_sec='900')
TABLE = PREFIX + 'comprofiler_plugin_beforeregverify'
PLUGINS = PREFIX + 'comprofiler_plugin'
records = []
lock = threading.Lock()
stamp = str(int(time.time()))

def command(args):
    return subprocess.check_output(args, cwd=SITE, text=True, stderr=subprocess.PIPE)

def sql(query):
    return command(['ddev', 'mysql', '-N', '-B', '-e', query]).strip()

def params(values):
    args = ','.join("'$.%s','%s'" % item for item in values.items())
    sql("UPDATE %s SET params=JSON_SET(params,%s) WHERE element='cbbeforeregverify'" % (PLUGINS, args))

def clean():
    sql("DELETE FROM %s WHERE email LIKE '%%@example.test'" % TABLE)

def save(name, value):
    (ROOT / name).write_text(value)

def request(folder, stream, seq, jar, func, fields=None):
    prefix = folder / ('s%s-%s-%s' % (stream, seq, func))
    args = ['curl', '-sS', '--max-time', '60', '-b', str(jar), '-c', str(jar), '-D', str(prefix)+'.headers', '-o', str(prefix)+'.html', '-w', '%{http_code}', URL+func]
    if fields is not None:
        args += ['--data', urllib.parse.urlencode(fields)]
    start = time.time_ns()/1e6
    status = command(args)
    end = time.time_ns()/1e6
    headers = pathlib.Path(str(prefix)+'.headers').read_text()
    body = pathlib.Path(str(prefix)+'.html').read_text()
    location = re.search(r'^location:\s*(.+)', headers, re.I|re.M)
    rec = dict(stream=stream, seq=seq, func=func, start_ms=start, end_ms=end, http_status=int(status), redirect=location.group(1).strip() if location else None)
    with lock:
        records.append(rec)
    return body, rec

def token(body):
    return re.search(r'name="([0-9a-f]{32})" value="1"', body).group(1)

def flash(body):
    match = re.search(r'<div id="system-message-container".*?(?=<div class="cb_template)', body, re.S)
    return html.unescape(re.sub('<[^>]+>', ' ', match.group(0) if match else '')).strip()

def post_follow(folder, i, seq, jar, tok, func, data):
    _, rec = request(folder, i, seq, jar, func, {tok:'1', **data})
    target = 'step_code' if rec['redirect'] and 'step_code' in rec['redirect'] else 'step_email'
    body, getrec = request(folder, i, str(seq)+'-follow', jar, target)
    rec['flash'] = flash(body)
    rec['follow_http_status'] = getrec['http_status']
    assert rec['http_status'] in (302,303), rec
    assert getrec['http_status'] == 200, getrec
    return body

def mail(folder):
    result = json.loads(command(['ddev','exec','curl -s "http://localhost:8025/api/v1/messages?limit=200"']))
    (folder/'mailpit-list.json').write_text(json.dumps(result, indent=2))
    return result['messages']

def overlap(items):
    events = sorted([(r['start_ms'], 1) for r in items]+[(r['end_ms'], -1) for r in items])
    count = peak = 0
    for _, delta in events:
        count += delta
        peak = max(peak,count)
    return peak

summary = []
log = SITE/'administrator/logs/cbbeforeregverify.php'
log_before = log.read_bytes() if log.exists() else b''
config_hash = hashlib.sha256((SITE/'configuration.php').read_bytes()).hexdigest()
# Read-only preflight, outside the try: a refused site must not reach the cleanup in finally.
save('ddev-describe.txt', command(['ddev','describe']))
save('fpm.txt', command(['ddev','exec','php-fpm -tt 2>&1']))
before = sql("SELECT JSON_REMOVE(params,'$.secret') FROM %s WHERE element='cbbeforeregverify'" % PLUGINS)
save('params-before.json',before)
if not all(json.loads(before).get(k)==v for k,v in DEFAULT.items()):
    raise SystemExit('refusing to run: plugin limit parameters are not the defaults (see params-before.json)')
if command(['curl','-sS','-o','/dev/null','-w','%{http_code}',URL.split('/index.php')[0]+'/']) != '200':
    raise SystemExit('refusing to run: the site did not answer 200')
try:
    save('web-before.log', command(['ddev','logs','-s','web']))
    for test in 'ABCD':
        for run in range(1,4):
            folder = ROOT/('%s%d' % (test,run)); folder.mkdir(exist_ok=True)
            params(DEFAULT); clean()
            changes = dict(rl_resend_enabled='0')
            if test in 'ABD': changes.update(rl_ip_short_max='50',rl_ip_day_max='50')
            if test in 'AC': changes.update(rl_email_short_max='20',rl_email_day_max='20')
            if test=='D': changes.update(rl_email_short_max='50',rl_email_day_max='50',rl_resend_enabled='1',rl_resend_cooldown_sec='60')
            params(changes)
            (folder/'params.json').write_text(sql("SELECT JSON_REMOVE(params,'$.secret') FROM %s WHERE element='cbbeforeregverify'" % PLUGINS))
            n = 6 if test=='A' else 4 if test=='D' else 10
            addresses = ['baseline-%s-%s%d-%d@example.test' % (stamp,test.lower(),run,i if test=='C' else 0) for i in range(n)]
            sessions = []
            for i in range(n):
                jar = folder/('s%d.cookies' % i)
                body,_ = request(folder,i,'prepare',jar,'step_email')
                tok = token(body)
                if test=='A':
                    body = post_follow(folder,i,'seed',jar,tok,'submit_email',{'email':addresses[i]})
                    assert addresses[i] in body and 'name="verification_code"' in body
                sessions.append((jar,tok))
            if test=='A':
                # Recheck all six flow pages after the final code issuance.
                for i,(jar,tok) in enumerate(sessions):
                    body,_ = request(folder,i,'flow-proof',jar,'step_code')
                    assert addresses[i] in body and 'name="verification_code"' in body
                codes=set()
                for msg in mail(folder):
                    if any(t['Address'] in addresses for t in msg.get('To',[])):
                        detail=json.loads(command(['ddev','exec','curl -s http://localhost:8025/api/v1/message/'+msg['ID']]))
                        (folder/('mail-'+msg['ID']+'.json')).write_text(json.dumps(detail,indent=2))
                        codes.update(re.findall(r'\b\d{6}\b',json.dumps(detail)))
                wrong=next('%06d' % x for x in range(1000000) if '%06d' % x not in codes)
                assert len(codes)>0
            else:
                assert sql("SELECT COUNT(*) FROM %s WHERE email LIKE '%%@example.test'" % TABLE)=='0'
            barrier=threading.Barrier(n)
            first=len(records)
            def worker(i):
                jar,tok=sessions[i]
                barrier.wait(timeout=30)
                for seq in range(10 if test=='A' else 1):
                    body=post_follow(folder,i,seq,jar,tok,'submit_code' if test=='A' else 'submit_email',{'verification_code':wrong} if test=='A' else {'email':addresses[i]})
                    tok=token(body)
            with concurrent.futures.ThreadPoolExecutor(max_workers=n) as pool:
                list(pool.map(worker,range(n)))
            burst=records[first:]
            posts=[r for r in burst if r['func'] in ('submit_email','submit_code')]
            count=int(sql("SELECT COUNT(*) FROM %s WHERE email LIKE '%%@example.test' AND %s" % (TABLE,"status='attempt' AND outcome='failed'" if test=='A' else "status='sent'")))
            rows=sql("SELECT id,email,status,outcome,sent_at,request_ip,note FROM %s WHERE email LIKE '%%@example.test' ORDER BY id" % TABLE)
            (folder/'rows.tsv').write_text(rows)
            messages=mail(folder)
            mail_count=sum(any(t['Address'] in addresses for t in m.get('To',[])) for m in messages)
            limit={'A':8,'B':3,'C':5,'D':1}[test]
            result=dict(test=test,run=run,limit=limit,rows=count,mailpit=mail_count,reproduced=count>limit,peak_post_overlap=overlap(posts),addresses=addresses,requests=posts)
            summary.append(result)
            (folder/'requests.json').write_text(json.dumps(burst,indent=2))
            save('summary.json',json.dumps(summary,indent=2))
            print(json.dumps({k:v for k,v in result.items() if k not in ('requests','addresses')}),flush=True)
            params(DEFAULT); clean()
finally:
    params(DEFAULT); clean()
    save('params-after.json',sql("SELECT JSON_REMOVE(params,'$.secret') FROM %s WHERE element='cbbeforeregverify'" % PLUGINS))
    save('cleanup-count.txt',sql("SELECT COUNT(*) FROM %s WHERE email LIKE '%%@example.test'" % TABLE))
    save('web-after.log',command(['ddev','logs','-s','web']))
    log_after=log.read_bytes() if log.exists() else b''
    save('plugin-log-new.txt',log_after[len(log_before):].decode(errors='replace') if log_after.startswith(log_before) else 'Log changed non-append; inspect manually.')
    save('config-hashes.txt',config_hash+'\n'+hashlib.sha256((SITE/'configuration.php').read_bytes()).hexdigest()+'\n')
    save('all-requests.json',json.dumps(records,indent=2))
