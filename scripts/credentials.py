#!/usr/bin/env python3
"""Fill secrets/ with one credential per file, none of them in git.

Identifiers are asked for, passwords are drawn. A file already filled is left
alone: to change a value, delete its file and run this again.

    ./scripts/credentials.py
    ./scripts/credentials.py --db-user camagru --admin-user smaitre
"""

import argparse
import secrets
import string
import sys
from pathlib import Path

FOLDER = Path(__file__).resolve().parent.parent / 'secrets'

# name: (question, default)
ASKED = {
    'db_user':     ('Postgres role', 'camagru'),
    'admin_user':  ('Camagru admin login', 'admin'),
    'admin_email': ('Camagru admin email', 'admin@camagru.local'),
}

DRAWN = {'db_password': 32, 'admin_password': 24}

# postgres reads db_* as uid 70 in the container, php reads admin_* as ours
MODES = {'db_user': 0o644, 'db_password': 0o644}


def read(name):
    path = FOLDER / name
    return path.read_text().strip() if path.is_file() else ''


def write(name, value):
    path = FOLDER / name
    path.write_text(value)
    path.chmod(MODES.get(name, 0o600))


def main():
    parser = argparse.ArgumentParser(description=__doc__,
                                     formatter_class=argparse.RawDescriptionHelpFormatter)
    for name in ASKED:
        parser.add_argument('--' + name.replace('_', '-'), dest=name)
    options = parser.parse_args()

    FOLDER.mkdir(exist_ok=True)

    for name, (question, default) in ASKED.items():
        if read(name):
            continue
        value = getattr(options, name)
        if value is None:
            if not sys.stdin.isatty():
                sys.exit(f'secrets/{name} is missing: pass --{name.replace("_", "-")} '
                         'or run this from a terminal')
            value = input(f'{question} [{default}]: ').strip() or default
        write(name, value)

    alphabet = string.ascii_letters + string.digits
    for name, size in DRAWN.items():
        if not read(name):
            write(name, ''.join(secrets.choice(alphabet) for _ in range(size)))

    print(f'{FOLDER} ready')
    print(f'  postgres  {read("db_user")} / {read("db_password")}')
    print(f'  admin     {read("admin_user")} <{read("admin_email")}> / {read("admin_password")}')


if __name__ == '__main__':
    main()
