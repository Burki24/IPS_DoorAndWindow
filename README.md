# IPS_DoorWindowState

Symcon module to aggregate door/window states from multiple sensor variables and provide one combined feedback state.

## Features

- Supports multiple sensor variables in one instance
- Works with different sensor value formats (boolean, numeric, text)
- Configurable `Open Value` per sensor
- Aggregated output state:
  - `0` = Unknown
  - `1` = Closed
  - `2` = Open

## Setup

1. Add this repository as a module source in Symcon.
2. Create an instance of **Door/Window State**.
3. In the `Sensors` list, add sensor variable IDs.
4. Set each sensor's `Open Value` (for example `1`, `true`, `open`).
5. Optionally disable rows using `Enabled`.

The instance variable `State` is updated automatically whenever one of the configured sensor values changes.
