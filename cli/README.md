# TechnoRapper CLI

A dependency-free Node.js command-line client for the public, read-only TechnoRapper Agent API.

## Requirements

- Node.js 20 or newer

## Install from source

From the repository root:

    cd cli
    npm install -g .

## Commands

    technorapper status
    technorapper articles --page 1 --per-page 10 --category mobile
    technorapper articles --after 2026-01-01 --before 2026-03-01
    technorapper article 139414
    technorapper search "AI phones"
    technorapper categories

The `articles` and `search` commands accept `--page` and `--per-page`; article lists also accept `--category`, `--after`, and `--before`. Collection page sizes are limited to 1–50.

Use `--base-url URL` to point the CLI at a compatible test server. Requests are GET-only. The CLI prints JSON to standard output, returns a non-zero exit status on errors, and reports `Retry-After` when the API returns HTTP 429.

npm registry publication is pending. After publication, install with:

    npm install -g technorapper-cli
