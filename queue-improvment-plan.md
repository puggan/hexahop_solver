# Queue Improvement Plan — `todo` bucket queue + parallel BFS

Notes captured for later implementation. Current code: `rust/solver_bfs.rs` (the
BFS loop + `Solver`), `rust/step.rs` (`GameState`, `GameStateGhost`).

## Context / current design

- `Solver.todo: BinaryHeap<GameStateGhost>`. `GameStateGhost { cost: u16,
  compressed_path: [u128; 3] }` (~48 B; 64 B after alignment).
- `Ord`/`PartialOrd`/`PartialEq` on `GameStateGhost` compare **cost only**
  (reversed → min-heap on cost). So it's uniform-cost / BFS-by-cost.
- `done: HashSet<u64>` (or u128 with `h128` feature) — visited set, ~304M
  entries on map 25.
- Hot loop: `todo.pop()` → `reproduce(start_state.clone(), &info)` → hash →
  dedup against `done` → expand 6 directions (`step`) → push children.

### Key properties (these make everything below valid)

1. **Pop cost is monotonically non-decreasing.** Every child costs
   `parent + extra_cost(≥1) + laser_cost(≥0)`, i.e. *strictly greater* than its
   parent (`step_out_of` starts `extra_cost = 1`). Never need to insert behind
   the current pop cost.
2. **Intra-level order is irrelevant.** All equal-cost states have equal
   priority; final `won.iter().max()` only needs the lowest-cost win.
3. **Cost is small and bounded.** Max `par` across all maps = **360**
   (`jq .[].par resources/hexahopmaps.json | sort -h | tail -n1` → 361 ⇒ values
   up to 360). `status()` already prunes anything over `max_cost` to `Dead`
   before it's pushed, so `todo` only ever holds cost `0..=360`.

## Reference performance data

Map 25 (slowest solved): `Done: 303,813,658 | Cost 216/216 | 12h07m | 7.04 GiB`.
- 303.8M / 43,649 s = **~6,960 states/s ≈ 144 µs per popped state.**
- That 144 µs is dominated by **`reproduce`**, which replays the *entire path
  from the start state on every pop* (`step_if_alive` folded over the whole
  path, ~100–150 steps × ~1.15 µs/step). The arithmetic matches wall-clock
  almost exactly → ~100% of runtime is path replay + `start_state.clone()`.
- The `BinaryHeap` itself is **nanoseconds** per op — negligible. **The bucket
  queue is NOT a single-thread speedup.** Its value is (a) cleanup, (b) small
  cache/memory win, (c) **it's the foundation for the parallel version.**

`MapState` = `[u8; 486]` + `Point` + 2 B ≈ **~490 B, fixed** (governs the
state-in-`todo` memory tradeoff in Option B below).

## Plan — sequenced, one explicit change per step

### Step 1 — Bucket queue (structural, behavior-identical)
- Replace `todo: BinaryHeap` with `[Vec<GameStateGhost>; 361]` (or
  `Vec<Vec<_>>` sized `max_cost+1`) + a `current_cost` cursor that only
  advances 0→360.
- Push: `buckets[cost].push(ghost)`. Pop: take from `buckets[current_cost]`;
  when empty, advance cursor to next non-empty bucket. Drop/`clear()` each
  drained bucket to release memory immediately.
- Delete the `Ord`/`PartialOrd`/`PartialEq` impls on `GameStateGhost`.
- Adjust the `won` pick (track min-cost win directly, or keep comparing cost).
- Overhead: ~361 Vec headers (~8.6 KB) — rounding error vs 7 GB.

### Step 2 — Drop the `cost` field from `GameStateGhost`
- The bucket index encodes cost; `compressed_path` is all that needs storing.
- Shrinks each entry and removes padding.

### Step 3 — Skip `reproduce` on duplicate pops (the cheap real speedup)
- Today duplicates pay the full ~144 µs replay *before* the `done` check, then
  get thrown away.
- Store the state-hash in the ghost at push time (it's already computed at
  push, line ~207). At pop: `if done.contains(&ghost.hash) { continue }` —
  skip replay; only `reproduce` the survivors.
- **Measure first:** add counters for peak `todo` length and the duplicate-pop
  ratio (pops hitting `done` / total pops). Ratio sets the expected payoff
  (e.g. 50% dup → ~2× on slow maps for +8 B/entry).

### Step 4 (optional, behavior-changing) — Early termination
- Levels are strictly increasing and any win costs `≥ parent+1`, so once
  `current_cost` reaches the best-win cost found so far, no remaining level can
  beat it → stop instead of draining every level to par. Map 25 ran to
  216/216; if optimal appeared earlier this is free time even single-threaded.
- Behavior change (currently drains all) → its own step.

### Step 5 — Parallel, level-synchronous BFS (the big win for 12 h maps)
Pattern: level-synchronous parallel BFS (≈ delta-stepping with unit buckets /
bulk-synchronous frontier). The bucket queue is what makes it safe:
- Monotonic cost ⇒ bucket `current_cost` is a closed frontier; expanding it
  only writes to strictly-future buckets ⇒ reads/writes on disjoint buckets.
- Intra-level order irrelevant ⇒ hand the whole cost-`c` bucket to
  `rayon::par_iter`.

```
for c in 0..=max_cost {
    let frontier = take(bucket[c]);          // read-only slice
    let children = frontier.par_iter()       // expand cost-c in parallel
        .flat_map(|g| expand(g))             // reproduce + 6 steps, thread-local out
        .collect();
    merge(children into future buckets);     // only sync point = level barrier
}
```
- Only synchronization is the per-level barrier — no per-pop queue locking. The
  expensive ~144 µs `reproduce`+`step` is embarrassingly parallel.
- **`done` becomes THE contention point** (not the queue). Needs a concurrent
  set (`dashmap`/`papaya`/sharded locks) with **atomic insert-if-absent**: only
  the thread that wins the insert expands the state — this dedups same-state
  duplicates landing in one bucket across threads. Parallel scaling is governed
  by this, plus memory bandwidth on the 304M-entry set.

## Implementation order recommendation

Per "baseline before optimize": only start once every map is confirmed solvable
at baseline. Then Step 1 → 2 (pure cleanup, safe). Step 3 after measuring dup
ratio. Step 5 is the real 12 h-map payoff; Step 4 is independent and optional.
