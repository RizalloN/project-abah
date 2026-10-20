"""Build auditable workbook references without changing the application database.

Convert supplied workbooks with markitdown_token_optimizer.py --stats-only first.
Every amount is read from the source; no per-person corrections are applied.
"""

import argparse
import collections
import datetime as dt
import hashlib
import json
from pathlib import Path

from openpyxl import load_workbook

AREA6_BRANCHES = {'KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'}

def identifier(value):
    value = str(value or '').strip()
    if value.endswith('.0'):
        value = value[:-2]
    return value.zfill(8) if value.isdigit() else value


def date_key(value):
    if isinstance(value, (dt.datetime, dt.date)):
        return value.strftime('%Y-%m-%d')
    text = str(value or '').strip()
    for pattern in ('%d/%m/%Y', '%Y-%m-%d', '%Y-%m-%d %H:%M:%S'):
        try:
            return dt.datetime.strptime(text, pattern).strftime('%Y-%m-%d')
        except ValueError:
            pass
    raise ValueError(f'Unsupported date: {text!r}')


def source_info(path):
    return {'file': path.name, 'sha256': hashlib.sha256(path.read_bytes()).hexdigest()}


def write_json(path, data):
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(data, ensure_ascii=False, separators=(',', ':')), encoding='utf-8')


def unassigned_rows(data):
    assigned = {r['pn'] for r in data['rows']}
    groups = {}
    for account in data['accounts']:
        if account['pn'] in assigned:
            continue
        group = (account['branch'],account['unit'],account['bc'],account['pn'])
        if group not in groups:
            groups[group] = {'pn':account['pn'],'nama':account['name'],'cabang':account['branch'],
                             'unit':account['unit'],'branch_code':account['bc'],'office_type':'kcp' if account['unit'].startswith('KCP ') else 'kc',
                             **{key:0 for key in ['lancar_deb','lancar_os','sml_deb','sml_os','npl_deb','npl_os','total_deb','total_os','realisasi_deb','realisasi_os']}}
        row = groups[group]
        tier = 'lancar' if account['kolek']==1 else 'sml' if account['kolek']==2 else 'npl' if isinstance(account['kolek'], (int,float)) and account['kolek']>2 else None
        if tier:
            row[tier+'_deb'] += 1
            row[tier+'_os'] += account['balance']
        row['total_deb'] += 1
        row['total_os'] += account['balance']
        if data['as_of'][:7]+'-01' <= account['realization_date'] <= data['as_of']:
            row['realisasi_deb'] += 1
            row['realisasi_os'] += account['plafon']
    return list(groups.values())


def build_rm(path):
    wb = load_workbook(path, read_only=True, data_only=True)
    as_of = date_key(wb['RM']['A2'].value)
    rows = []
    for line, values in enumerate(wb['RM'].iter_rows(values_only=True), 1):
        if line < 6 or not isinstance(values[0], (int, float)):
            continue
        unit = str(values[5]).split('--', 1)[-1].strip().upper()
        row = {'pn': identifier(values[2]), 'nama': str(values[3]).strip(),
               'cabang': 'KC '+str(values[1]).strip().upper(), 'branch_code': str(values[4]),
               'unit': unit, 'office_type': 'kcp' if unit.startswith('KCP ') else 'kc',
               'source_row': line}
        for field, index in [('lancar_deb',6),('lancar_os',7),('sml_deb',8),('sml_os',9),
                             ('npl_deb',10),('npl_os',11),('total_deb',12),('total_os',13),
                             ('realisasi_deb',14),('realisasi_os',15),('target_os',16)]:
            row[field] = values[index] * (1000000 if field.endswith('_os') else 1)
        rows.append(row)
    if len(set(r['pn'] for r in rows)) != len(rows):
        raise ValueError('RM sheet has duplicate PN identities.')
    names = {r['pn']: r['nama'] for r in rows}
    accounts = []
    for line, values in enumerate(wb['RITEL BERJALAN'].iter_rows(values_only=True), 1):
        if line == 1 or not values[25]:
            continue
        pn = identifier(values[1])
        pn_name = str(values[53] or '').split(' - ',1)[-1].strip()
        accounts.append({'account':str(values[25]).strip(), 'cif':str(values[36]).strip(),
                         'debtor_name':str(values[26]).strip(), 'pn':pn, 'name':names.get(pn,pn_name),
                         'branch':str(values[20]).strip().upper(), 'unit':str(values[22]).strip().upper(),
                         'bc':str(values[21]), 'loan_type':str(values[24]).strip(),
                         'kolek':values[51], 'balance':values[63], 'plafon':values[62],
                         'realization_date':date_key(values[32]), 'source_row':line})
    if len(set(a['account'] for a in accounts)) != len(accounts):
        raise ValueError('RITEL BERJALAN has duplicate accounts.')
    current = [a for a in accounts if as_of[:7]+'-01' <= a['realization_date'] <= as_of]
    by_pn = collections.defaultdict(lambda:{'deb':0,'amount':0})
    for account in current:
        by_pn[account['pn']]['deb'] += 1
        by_pn[account['pn']]['amount'] += account['plafon']
    mismatches = [r['pn'] for r in rows if r['realisasi_deb'] != by_pn[r['pn']]['deb']
                  or abs(r['realisasi_os']-by_pn[r['pn']]['amount']) > 0.01]
    if mismatches:
        raise ValueError('RM realization does not reconcile to accounts: '+', '.join(mismatches))
    position_by_pn = collections.defaultdict(lambda:{'deb':0,'amount':0})
    for account in accounts:
        position_by_pn[account['pn']]['deb'] += 1
        position_by_pn[account['pn']]['amount'] += account['balance']
    position_mismatches = [r['pn'] for r in rows if r['total_deb'] != position_by_pn[r['pn']]['deb']
                           or abs(r['total_os']-position_by_pn[r['pn']]['amount']) > 0.01]
    return {'version':1, 'as_of':as_of, 'source':{**source_info(path),'sheet':'RM','accounts_sheet':'RITEL BERJALAN'},
            'scope':'RO MALANG', 'realization_basis':'gross_plafond', 'rows':rows, 'accounts':accounts,
            'source_counts':{'people':len(rows),'accounts':len(accounts),'realization_accounts':len(current)},
            'audit':{'per_person_realization_mismatches':mismatches,
                     'per_person_position_mismatches':position_mismatches,
                     'all_realization_amount':sum(a['plafon'] for a in current),
                     'assigned_realization_amount':sum(r['realisasi_os'] for r in rows),
                     'assigned_realization_accounts':sum(r['realisasi_deb'] for r in rows)}}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--rm-workbook', type=Path)
    parser.add_argument('--output-dir', type=Path, required=True)
    parser.add_argument('--area6-only', action='store_true')
    args = parser.parse_args()
    if not args.rm_workbook:
        parser.error('--rm-workbook is required')
    data = build_rm(args.rm_workbook)
    if args.area6_only:
        data['scope'] = 'AREA 6'
        data['rows'] = [r for r in data['rows'] if r['cabang'] in AREA6_BRANCHES]
        data['accounts'] = [a for a in data['accounts'] if a['branch'] in AREA6_BRANCHES]
        data['scope_counts'] = {'people':len(data['rows']),'accounts':len(data['accounts'])}
    data['unassigned_rows'] = unassigned_rows(data)
    target = args.output_dir / 'rm-kur-productivity-reference.candidate.json'
    write_json(target, data)
    print(json.dumps({'output':str(target),'as_of':data['as_of'],'counts':data['source_counts'],'audit':data['audit']}))


if __name__ == '__main__':
    main()
