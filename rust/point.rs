use crate::direction::Direction;
use std::fmt;
use std::ops::Neg;
use std::ops::Mul;
use std::ops::Add;
use std::ops::Sub;

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

    pub fn projectile(&self, dir: Direction) -> Projectile {
        Projectile { point: *self, dir }
    }

    pub fn neighbours(&self) -> [Projectile; 6] {
        Projectile::all(*self).map(|p| p.forward(1))
    }
}

impl Add for Point {
    type Output = Point;
    fn add(self, other: Point) -> Point {
        Point::new(self.x + other.x, self.y + other.y)
    }
}

impl Sub for Point {
    type Output = Point;
    fn sub(self, other: Point) -> Point {
        Point::new(self.x - other.x, self.y - other.y)
    }
}

impl Neg for Point {
    type Output = Point;
    fn neg(self) -> Point {
        Point::new(-self.x, -self.y)
    }
}

impl Mul<i8> for Point {
    type Output = Point;
    fn mul(self, scalar: i8) -> Point {
        Point::new(self.x * scalar, self.y * scalar)
    }
}

impl fmt::Display for Point {
    fn fmt(&self, f: &mut fmt::Formatter) -> fmt::Result {
        write!(f, "({}, {})", self.x, self.y)
    }
}

#[derive(Debug, Clone, Copy, Hash, Eq, PartialEq)]
pub struct Boundary {
    pub low: Point,
    pub high: Point,
}

impl Boundary {
    /// Build a boundary from a parsed [x_min, x_max, y_min, y_max] bounds array.
    pub fn from_parsed(bounds: [u8; 4]) -> Self {
        Boundary {
            low: Point::new(bounds[0] as i8, bounds[2] as i8),
            high: Point::new(bounds[1] as i8, bounds[3] as i8),
        }
    }

    /// The inclusive size spanned by the boundary, as a Point.
    pub fn size(&self) -> Point {
        self.high - self.low + Point::new(1, 1)
    }

    pub fn expand(&self, n: i8) -> Boundary {
        Boundary {
            low: self.low - Point::new(n, n),
            high: self.high + Point::new(n, n),
        }
    }
}

#[derive(Debug, Clone, Copy, Hash, Eq, PartialEq)]
pub struct Projectile {
    pub point: Point,
    pub dir: Direction,
}

impl Projectile {
    pub fn all(point: Point) -> [Projectile; 6] {
        Direction::flat().map(|dir| Projectile { point, dir })
    }

    pub fn forward(&self, length: i8) -> Projectile {
        Projectile {
            point: self.point + self.dir.offset(length),
            dir: self.dir,
        }
    }
}
