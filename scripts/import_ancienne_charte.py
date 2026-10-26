#!/usr/bin/env python3
"""Transfere uniquement les chartes de var/datensis_local.sqlite3 vers var/eclient.sqlite3.
Ne touche jamais aux articles, factures ou tables DATENSIS. Ne remplace pas les chartes
existantes sauf avec --overwrite.
"""
import argparse
import sqlite3
import sys
from pathlib import Path

COLS = [
    'client_id', 'nom_entreprise', 'couleur_principale', 'couleur_secondaire',
    'couleur_accent', 'couleur_fond', 'couleur_navbar', 'couleur_carte',
    'logo', 'logo_url', 'date_creation', 'date_modification',
]
DEFAULTS = {
    'nom_entreprise': '',
    'couleur_principale': '#667eea',
    'couleur_secondaire': '#764ba2',
    'couleur_accent': '#ee6c4d',
    'couleur_fond': '#f3f4f6',
    'couleur_navbar': '#ffffff',
    'couleur_carte': '#ffffff',
}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--old', default='var/datensis_local.sqlite3', help='Ancienne SQLite locale')
    parser.add_argument('--new', default='var/eclient.sqlite3', help='Nouvelle SQLite e-client')
    parser.add_argument('--overwrite', action='store_true', help='Remplacer les chartes deja presentes')
    args = parser.parse_args()
    old, new = Path(args.old), Path(args.new)
    if not old.is_file() or not new.is_file() or old.resolve() == new.resolve():
        parser.error('Verifiez que les deux fichiers SQLite existent et sont distincts.')
    source = sqlite3.connect(f'file:{old.resolve().as_posix()}?mode=ro', uri=True)
    target = sqlite3.connect(f'file:{new.resolve().as_posix()}?mode=rw', uri=True)
    try:
        source.row_factory = sqlite3.Row
        old_table = source.execute("SELECT 1 FROM sqlite_master WHERE name='myfacturation_documentpersonnalisation' AND type='table'").fetchone()
        new_table = target.execute("SELECT 1 FROM sqlite_master WHERE name='eclient_charte' AND type='table'").fetchone()
        if not old_table or not new_table:
            raise RuntimeError('Table ancienne/nouvelle absente. Verifiez les bases fournies.')
        old_cols = {row['name'] for row in source.execute("PRAGMA table_info('myfacturation_documentpersonnalisation')")}
        available = [c for c in COLS if c in old_cols]
        if 'client_id' not in available:
            raise RuntimeError('La table ancienne ne possede pas client_id.')
        rows = source.execute('SELECT '+', '.join(available)+' FROM myfacturation_documentpersonnalisation').fetchall()
        sql = (
            'INSERT INTO eclient_charte ('+', '.join(COLS)+') VALUES ('+', '.join('?' for _ in COLS)+') '
            'ON CONFLICT(client_id) DO '+(
                'UPDATE SET '+', '.join(f'{c}=excluded.{c}' for c in COLS if c != 'client_id')
                if args.overwrite else 'NOTHING'
            )
        )
        with target:
            for row in rows:
                payload = dict(row)
                vals = [payload.get(c) if payload.get(c) is not None else DEFAULTS.get(c) for c in COLS]
                target.execute(sql, vals)
        print(f'{len(rows)} charte(s) lue(s) dans {old}; {target.execute("SELECT COUNT(*) FROM eclient_charte").fetchone()[0]} dans {new}.')
    finally:
        target.close()
        source.close()


if __name__ == '__main__':
    try:
        main()
    except (sqlite3.Error, RuntimeError) as exc:
        print(f'ERREUR : {exc}', file=sys.stderr)
        sys.exit(1)
