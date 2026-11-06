import fs from 'node:fs'
import path from 'node:path'
import { describe, expect, it } from 'vitest'

export const REQUIRED_TOKENS = [
  // Primary action
  '--color-primary',
  '--color-primary-hover',
  '--color-primary-active',
  '--color-on-primary',

  // Secondary action
  '--color-secondary',
  '--color-secondary-hover',
  '--color-secondary-active',
  '--color-on-secondary',

  // Accent
  '--color-accent',
  '--color-accent-hover',

  // Focus
  '--color-focus',

  // Text
  '--color-fg',
  '--color-fg-secondary',
  '--color-fg-muted',
  '--color-fg-subtle',
  '--color-fg-placeholder',

  // Backgrounds
  '--color-canvas',
  '--color-surface',
  '--color-surface-muted',

  // Borders
  '--color-border',
  '--color-border-strong',
  '--color-border-subtle',

  // Text on tones
  '--color-on-tone',

  // Status tones (neutral, info, success, warning, attention, danger, highlight)
  ...['neutral', 'info', 'success', 'warning', 'attention', 'danger', 'highlight'].flatMap((tone) => [
    `--color-${tone}`,
    `--color-${tone}-hover`,
    `--color-${tone}-soft`,
    `--color-${tone}-subtle`,
    `--color-${tone}-border`,
    `--color-${tone}-fg`,
  ]),

  // Chart series
  '--color-chart-created',
  '--color-chart-resolved',

  // Chart SLA status (within and met share one color, breached and missed another)
  '--color-sla-within',
  '--color-sla-approaching',
  '--color-sla-breached',
]

describe('Design System - Tokens', () => {
  it('asserts that index.css defines every D2 token in @theme', () => {
    const cssPath = path.resolve(process.cwd(), 'src/index.css')
    const indexCss = fs.readFileSync(cssPath, 'utf-8')
    const themeMatch = indexCss.match(/@theme\s*\{([^}]+)\}/s)
    expect(themeMatch).toBeTruthy()
    const themeContent = themeMatch ? themeMatch[1] : ''
    const cleanTheme = themeContent.replace(/\/\*[\s\S]*?\*\//g, '')

    const missingTokens = REQUIRED_TOKENS.filter((token) => !cleanTheme.includes(`${token}:`))
    expect(missingTokens, `Missing tokens in index.css: ${missingTokens.join(', ')}`).toEqual([])
  })
})
