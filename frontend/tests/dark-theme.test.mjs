import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { equal } from 'node:assert';

test('dark theme loads main.scss', () => {
  const content = readFileSync('./src/styles/main.scss', 'utf8');
  equal(content.includes('--selection: #264f78'), true);
});