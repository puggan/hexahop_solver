use crate::direction::Direction;
use crate::map::MapInfo;
use crate::map::MapState;
use crate::map::MapStatus;

#[derive(Clone, Debug)]
pub struct GameState {
    pub state: MapState,
    pub path: Vec<Direction>,
    pub cost: u16,
}

impl GameState {
    pub fn new(state: MapState) -> Self {
        Self {
            state,
            path: Vec::new(),
            cost: 0,
        }
    }

    pub fn status(&self, map_info: MapInfo, current_cost: u16, max_cost: &Option<u16>) -> MapStatus {
        self.state.status(&map_info, current_cost, max_cost)
    }

    pub fn get_path_hash(&self) -> Vec<u64> {
        let mut hashes = Vec::new();
        let mut hash: u64 = 0;
        let mut part_count = 0;
        for &dir in &self.path {
            hash = (hash << 3) | (dir as u64);
            part_count += 1;
            if part_count >= 20  {
                hashes.push(hash);
                hash = 0;
                part_count = 0;
            }
        }
        hashes
    }
}