#!/usr/bin/env node
import { pathToFileURL } from 'node:url';
import { resolve } from 'node:path';

export const API_BASE_URL = 'https://technorapper.com/wp-json/tr/v1/';
export const VERSION = '1.0.0';

const OPTION_NAMES = new Map([
  ['base-url', 'baseUrl'],
  ['page', 'page'],
  ['per-page', 'perPage'],
  ['category', 'category'],
  ['after', 'after'],
  ['before', 'before'],
]);

export const HELP = [
  'TechnoRapper CLI ' + VERSION,
  '',
  'Read published content from the public, read-only TechnoRapper Agent API.',
  '',
  'Usage:',
  '  technorapper status [--base-url URL]',
  '  technorapper articles [--page N] [--per-page N] [--category SLUG] [--after DATE] [--before DATE]',
  '  technorapper article ID',
  '  technorapper search QUERY [--page N] [--per-page N]',
  '  technorapper categories',
  '  technorapper --help',
  '  technorapper --version',
  '',
  'The API permits low-volume public reads without authentication. Respect HTTP 429',
  'and the Retry-After response header. Use --base-url to point at a test server.',
  '',
].join('\n');

export function parseArguments(argv) {
  if (argv.includes('--help') || argv.includes('-h') || argv[0] === 'help') {
    return { command: 'help', options: {}, positional: [] };
  }
  if (argv.includes('--version') || argv.includes('-v')) {
    return { command: 'version', options: {}, positional: [] };
  }

  const command = argv[0];
  if (!command) {
    throw new Error('Choose a command. Run technorapper --help for usage.');
  }

  const options = {};
  const positional = [];
  for (let index = 1; index < argv.length; index += 1) {
    const arg = argv[index];
    if (!arg.startsWith('--')) {
      positional.push(arg);
      continue;
    }

    const separator = arg.indexOf('=');
    const rawName = separator === -1 ? arg.slice(2) : arg.slice(2, separator);
    const name = OPTION_NAMES.get(rawName);
    if (!name) {
      throw new Error('Unknown option: --' + rawName);
    }

    let value = separator === -1 ? argv[index + 1] : arg.slice(separator + 1);
    if (separator === -1) {
      index += 1;
    }
    if (value === undefined || value === '' || value.startsWith('--')) {
      throw new Error('Option --' + rawName + ' requires a value.');
    }
    options[name] = value;
  }

  return { command, options, positional };
}

function integerOption(options, name, defaultValue, min, max) {
  if (options[name] === undefined) {
    return defaultValue;
  }
  const value = Number(options[name]);
  if (!Number.isInteger(value) || value < min || (max !== undefined && value > max)) {
    const range = max === undefined ? 'at least ' + min : min + ' to ' + max;
    throw new Error('--' + (name === 'perPage' ? 'per-page' : name) + ' must be an integer from ' + range + '.');
  }
  return value;
}

function assertOnlyOptions(options, allowed) {
  for (const key of Object.keys(options)) {
    if (!allowed.has(key)) {
      throw new Error('Option --' + (key === 'perPage' ? 'per-page' : key) + ' is not supported for this command.');
    }
  }
}

export function buildRequest(parsed) {
  const { command, options, positional } = parsed;
  let endpoint;
  const params = new URLSearchParams();
  const allowed = new Set(['baseUrl']);

  switch (command) {
    case 'status':
      endpoint = 'status';
      break;
    case 'categories':
      endpoint = 'categories';
      break;
    case 'articles':
      endpoint = 'articles';
      allowed.add('page');
      allowed.add('perPage');
      allowed.add('category');
      allowed.add('after');
      allowed.add('before');
      params.set('page', String(integerOption(options, 'page', 1, 1)));
      params.set('per_page', String(integerOption(options, 'perPage', 20, 1, 50)));
      for (const key of ['category', 'after', 'before']) {
        if (options[key] !== undefined) {
          params.set(key, options[key]);
        }
      }
      break;
    case 'article':
      endpoint = 'articles';
      if (positional.length !== 1 || !/^[1-9]\d*$/.test(positional[0])) {
        throw new Error('Provide one numeric published article ID.');
      }
      endpoint += '/' + positional[0];
      break;
    case 'search': {
      endpoint = 'search';
      allowed.add('page');
      allowed.add('perPage');
      const query = positional.join(' ').trim();
      if (query.length < 2) {
        throw new Error('Search needs at least two characters.');
      }
      params.set('q', query);
      params.set('page', String(integerOption(options, 'page', 1, 1)));
      params.set('per_page', String(integerOption(options, 'perPage', 20, 1, 50)));
      break;
    }
    default:
      throw new Error('Unknown command: ' + command + '. Run technorapper --help for usage.');
  }

  if (!['article', 'search'].includes(command) && positional.length !== 0) {
    throw new Error('This command does not accept positional arguments.');
  }
  assertOnlyOptions(options, allowed);

  const baseUrl = new URL(options.baseUrl || API_BASE_URL);
  if (baseUrl.protocol !== 'https:' && baseUrl.protocol !== 'http:') {
    throw new Error('--base-url must use HTTP or HTTPS.');
  }
  if (!baseUrl.pathname.endsWith('/')) {
    baseUrl.pathname += '/';
  }
  const url = new URL(endpoint, baseUrl);
  for (const [key, value] of params) {
    url.searchParams.set(key, value);
  }
  return url;
}

export async function runCli(argv, io = {}) {
  const stdout = io.stdout || process.stdout;
  const stderr = io.stderr || process.stderr;
  const fetchImpl = io.fetchImpl || globalThis.fetch;

  try {
    const parsed = parseArguments(argv);
    if (parsed.command === 'help') {
      stdout.write(HELP);
      return 0;
    }
    if (parsed.command === 'version') {
      stdout.write(VERSION + '\n');
      return 0;
    }

    const url = buildRequest(parsed);
    const response = await fetchImpl(url, {
      method: 'GET',
      headers: { Accept: 'application/json' },
    });

    let payload = null;
    try {
      payload = await response.json();
    } catch {
      payload = null;
    }

    if (response.status === 429) {
      const retryAfter = response.headers.get('Retry-After') || payload?.data?.retry_after;
      throw new Error('API rate limit reached. Retry after ' + (retryAfter || 'the reset interval') + '.');
    }
    if (!response.ok) {
      const message = payload?.message || response.statusText || 'request failed';
      throw new Error('API returned HTTP ' + response.status + ': ' + message);
    }

    stdout.write(JSON.stringify(payload, null, 2) + '\n');
    return 0;
  } catch (error) {
    stderr.write('technorapper: ' + (error instanceof Error ? error.message : String(error)) + '\n');
    return 1;
  }
}

if (process.argv[1] && import.meta.url === pathToFileURL(resolve(process.argv[1])).href) {
  process.exitCode = await runCli(process.argv.slice(2));
}
