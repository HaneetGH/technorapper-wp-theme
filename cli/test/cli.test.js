import test from 'node:test';
import assert from 'node:assert/strict';
import { buildRequest, parseArguments, runCli } from '../bin/technorapper.js';

test('article command builds a stable detail URL', () => {
  const url = buildRequest(parseArguments(['article', '139414']));
  assert.equal(url.href, 'https://technorapper.com/wp-json/tr/v1/articles/139414');
});

test('article command rejects non-numeric IDs', () => {
  assert.throws(
    () => buildRequest(parseArguments(['article', 'latest'])),
    /numeric published article ID/
  );
});

test('articles command encodes bounded filters and pagination', () => {
  const url = buildRequest(parseArguments([
    'articles',
    '--page', '2',
    '--per-page', '5',
    '--category', 'mobile',
    '--after', '2026-01-01',
  ]));
  assert.equal(url.pathname, '/wp-json/tr/v1/articles');
  assert.equal(url.searchParams.get('page'), '2');
  assert.equal(url.searchParams.get('per_page'), '5');
  assert.equal(url.searchParams.get('category'), 'mobile');
  assert.equal(url.searchParams.get('after'), '2026-01-01');
});

test('categories command maps to the public category endpoint', () => {
  const url = buildRequest(parseArguments(['categories']));
  assert.equal(url.href, 'https://technorapper.com/wp-json/tr/v1/categories');
});

test('search command joins terms and uses the supplied API base URL', () => {
  const url = buildRequest(parseArguments([
    'search', 'AI', 'phones', '--base-url', 'http://127.0.0.1:8080/wp-json/tr/v1',
  ]));
  assert.equal(url.origin, 'http://127.0.0.1:8080');
  assert.equal(url.pathname, '/wp-json/tr/v1/search');
  assert.equal(url.searchParams.get('q'), 'AI phones');
});

test('successful requests print the JSON response', async () => {
  let requestedUrl;
  let output = '';
  let errors = '';
  const code = await runCli(['status'], {
    fetchImpl: async (url) => {
      requestedUrl = url.href;
      return new Response('{"status":"ok"}', {
        status: 200,
        headers: { 'content-type': 'application/json' },
      });
    },
    stdout: { write: (value) => { output += value; } },
    stderr: { write: (value) => { errors += value; } },
  });

  assert.equal(code, 0);
  assert.equal(requestedUrl, 'https://technorapper.com/wp-json/tr/v1/status');
  assert.deepEqual(JSON.parse(output), { status: 'ok' });
  assert.equal(errors, '');
});

test('429 responses tell clients when to retry', async () => {
  let errors = '';
  const code = await runCli(['status'], {
    fetchImpl: async () => new Response('{"message":"Too many requests."}', {
      status: 429,
      headers: { 'content-type': 'application/json', 'Retry-After': '17' },
    }),
    stdout: { write: () => {} },
    stderr: { write: (value) => { errors += value; } },
  });

  assert.equal(code, 1);
  assert.match(errors, /Retry after 17/);
});

test('invalid pagination fails before making a request', async () => {
  let called = false;
  let errors = '';
  const code = await runCli(['articles', '--per-page', '51'], {
    fetchImpl: async () => { called = true; },
    stdout: { write: () => {} },
    stderr: { write: (value) => { errors += value; } },
  });

  assert.equal(code, 1);
  assert.equal(called, false);
  assert.match(errors, /per-page must be an integer/);
});

test('help is readable without making a request', async () => {
  let called = false;
  let output = '';
  const code = await runCli(['--help'], {
    fetchImpl: async () => { called = true; },
    stdout: { write: (value) => { output += value; } },
    stderr: { write: () => {} },
  });

  assert.equal(code, 0);
  assert.equal(called, false);
  assert.match(output, /read-only TechnoRapper Agent API/i);
});
