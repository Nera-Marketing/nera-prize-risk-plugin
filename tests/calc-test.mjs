// CLI test for the JS calculation engine: node tests/calc-test.mjs
// Asserts a worked example, same values as calc-test.php. Exits 1 on any failure.
import { createRequire } from 'node:module'

const require = createRequire(import.meta.url)
const c = require('../assets/js/calc.js')

const prizeCost = 42000, otherCosts = 0, ticketPrice = 4.99, totalTickets = 15000, feePct = 0.014
const paid = 6000, free = 0

const net = c.net_price(ticketPrice, feePct)
const totalCost = c.total_cost(prizeCost, otherCosts)
const beTix = c.break_even_tix(totalCost, net)
const bePct = c.break_even_pct(beTix, totalTickets)
const soldOut = c.sold_out_result(0, c.max_remaining(totalTickets, 0, 0, net), totalCost)
const rev6000 = c.revenue_net(paid * ticketPrice, feePct)
const pos6000 = c.position(rev6000, totalCost)
const soldOut6 = c.sold_out_result(rev6000, c.max_remaining(totalTickets, paid, free, net), totalCost)

let fail = 0
const check = (label, actual, expected, dp) => {
  const shown = actual === null ? 'null' : String(Number(actual.toFixed(dp)))
  const ok = actual !== null && Math.abs(actual - expected) < 10 ** -dp / 2
  if (!ok) fail++
  console.log(`${label} ${shown}${ok ? '' : `  FAIL (expected ${expected})`}`)
}

check('net_price', net, 4.92014, 5)
check('break_even_tix', beTix, 8537, 0)
check('break_even_pct', bePct, 0.5691, 4)
check('sold_out_result', soldOut, 31802.1, 2)
check('position@6000', pos6000, -12479.16, 2)
check('sold_out_result@6000', soldOut6, 31802.1, 2)
check('sell_through@6000', c.sell_through(paid, free, totalTickets), 0.4, 4)

if (Math.abs(soldOut - (4.92 * totalTickets - totalCost)) < 1) { fail++; console.log('net_price was rounded before use  FAIL') }
if (c.break_even_tix(100, 0) !== null || c.break_even_pct(10, 0) !== null || c.sell_through(1, 0, 0) !== null) { fail++; console.log('zero guards  FAIL') }

if (c.can_break_even(771, 200) !== false || c.can_break_even(8537, 15000) !== true || c.can_break_even(200, 200) !== true || c.can_break_even(null, 200) !== true) { fail++; console.log('can_break_even  FAIL') } else console.log('can_break_even 771/200 false, 8537/15000 true, 200/200 true')

console.log(fail ? `FAILED: ${fail}` : 'OK')
process.exit(fail ? 1 : 0)
