#!/usr/bin/env python3
"""Create one confirmed account, the way a visitor would.

Sign-up over HTTP, confirmation link read from MailHog, then a login to check
the account really opens. The email defaults to <username>@camagru.local and
the password is drawn when it is not given.

    ./scripts/user.py alice
    ./scripts/user.py alice alice@camagru.local Sunflower42
"""

import argparse
import secrets
import string
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from seed import Client, Erreur, attendre, inscrire  # noqa: E402


def draw():
    """A password the sign-up form accepts: letters and digits, long enough."""
    alphabet = string.ascii_letters + string.digits
    while True:
        word = ''.join(secrets.choice(alphabet) for _ in range(12))
        if any(c.isalpha() for c in word) and any(c.isdigit() for c in word):
            return word


def main():
    parser = argparse.ArgumentParser(description=__doc__.split('\n')[0],
                                     formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument('username')
    parser.add_argument('email', nargs='?')
    parser.add_argument('password', nargs='?')
    parser.add_argument('--url', default='http://localhost:8080')
    parser.add_argument('--mailhog', default='http://localhost:8025')
    options = parser.parse_args()

    email = options.email or f'{options.username}@camagru.local'
    password = options.password or draw()

    if not attendre(options.url):
        sys.exit(f'{options.url} does not answer: is make up done?')
    if not attendre(options.mailhog + '/api/v2/messages'):
        sys.exit(f'{options.mailhog} does not answer: MailHog carries the sign-up link.')

    client = Client(options.url, options.username)
    try:
        inscrire(client, options.mailhog, options.username, email, password)
    except Erreur as erreur:
        sys.exit(f'Account not created: {erreur}')

    print(f'{options.username} <{email}> / {password}')
    print(f'{options.url}/login')


if __name__ == '__main__':
    main()
