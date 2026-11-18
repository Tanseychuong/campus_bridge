// Runs Vitest with a normalised working directory.
//
// On Windows, editors such as VS Code can open terminals at "c:\..." (lower-case
// drive letter). Vitest then loads two copies of itself and every suite fails
// with "Vitest failed to find the current suite". Starting Vitest from the
// upper-case path avoids that. On macOS/Linux this script changes nothing.
import { spawnSync } from "node:child_process";
import { fileURLToPath } from "node:url";

const cwd = process.cwd().replace(/^[a-z]:/, (drive) => drive.toUpperCase());
const cli = fileURLToPath(new URL("../node_modules/vitest/vitest.mjs", import.meta.url));
const result = spawnSync(process.execPath, [cli, ...process.argv.slice(2)], {
  cwd,
  stdio: "inherit",
});
process.exit(result.status ?? 1);
