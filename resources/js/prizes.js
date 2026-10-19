import { setupPatternEditor } from './pattern.js';

const select = document.querySelector('[data-rule-select]');
const block = document.querySelector('[data-pattern-block]');
const input = document.querySelector('[data-pattern-input]');
const grid = document.querySelector('[data-pattern-grid]');
const [rows, cols] = window.bingoCardDimensions;

setupPatternEditor(select, block, input, grid, rows, cols);
