use std::fmt;
use std::ops::Add;

#[derive(Debug, Clone, Copy, Hash, Eq, PartialEq)]
pub struct Point {
    pub x: i8,
    pub y: i8,
}

impl Point {
    pub fn new(x: i8, y: i8) -> Self {
        Self { x, y }
    }

    pub fn index(&self, height: u8) -> usize {
        self.x as usize * height as usize + self.y as usize
    }

    pub fn valid(&self, height: u8, width: u8) -> bool {
        self.x >= 0 && self.y >= 0 && self.x < width as i8 && self.y < height as i8
    }
}

impl Add for Point {
    type Output = Point;
    fn add(self, other: Point) -> Point {
        Point::new(self.x + other.x, self.y + other.y)
    }
}

impl fmt::Display for Point {
    fn fmt(&self, f: &mut fmt::Formatter) -> fmt::Result {
        write!(f, "({}, {})", self.x, self.y)
    }
}
