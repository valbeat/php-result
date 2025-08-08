// Rustの標準的なResult型のテスト例
// 参考: https://doc.rust-lang.org/std/result/enum.Result.html

#[cfg(test)]
mod tests {
    use super::*;

    // 基本的な型判定のテスト
    #[test]
    fn test_is_ok() {
        let x: Result<i32, &str> = Ok(2);
        assert_eq!(x.is_ok(), true);
        
        let x: Result<i32, &str> = Err("Nothing here");
        assert_eq!(x.is_ok(), false);
    }

    #[test]
    fn test_is_err() {
        let x: Result<i32, &str> = Ok(2);
        assert_eq!(x.is_err(), false);
        
        let x: Result<i32, &str> = Err("Nothing here");
        assert_eq!(x.is_err(), true);
    }

    // is_ok_and / is_err_and のテスト (Rust 1.70.0+)
    #[test]
    fn test_is_ok_and() {
        let x: Result<u32, &str> = Ok(2);
        assert_eq!(x.is_ok_and(|x| x > 1), true);
        assert_eq!(x.is_ok_and(|x| x > 4), false);
        
        let x: Result<u32, &str> = Err("hey");
        assert_eq!(x.is_ok_and(|x| x > 1), false);
    }

    #[test]
    fn test_is_err_and() {
        let x: Result<u32, &str> = Err("error");
        assert_eq!(x.is_err_and(|e| e.len() > 3), true);
        assert_eq!(x.is_err_and(|e| e.len() < 3), false);
        
        let x: Result<u32, &str> = Ok(2);
        assert_eq!(x.is_err_and(|e| e.len() > 3), false);
    }

    // unwrap系のテスト
    #[test]
    fn test_unwrap_ok() {
        let x: Result<u32, &str> = Ok(2);
        assert_eq!(x.unwrap(), 2);
    }

    #[test]
    #[should_panic(expected = "called `Result::unwrap()` on an `Err` value")]
    fn test_unwrap_err_panics() {
        let x: Result<u32, &str> = Err("emergency failure");
        x.unwrap(); // panics
    }

    #[test]
    fn test_unwrap_err() {
        let x: Result<u32, &str> = Err("emergency failure");
        assert_eq!(x.unwrap_err(), "emergency failure");
    }

    #[test]
    #[should_panic(expected = "called `Result::unwrap_err()` on an `Ok` value")]
    fn test_unwrap_err_on_ok_panics() {
        let x: Result<u32, &str> = Ok(2);
        x.unwrap_err(); // panics
    }

    #[test]
    fn test_unwrap_or() {
        let default = 2;
        let x: Result<u32, &str> = Ok(9);
        assert_eq!(x.unwrap_or(default), 9);
        
        let x: Result<u32, &str> = Err("error");
        assert_eq!(x.unwrap_or(default), default);
    }

    #[test]
    fn test_unwrap_or_else() {
        fn count(x: &str) -> usize { x.len() }
        
        assert_eq!(Ok(2).unwrap_or_else(count), 2);
        assert_eq!(Err("foo").unwrap_or_else(count), 3);
    }

    // map系のテスト
    #[test]
    fn test_map() {
        let x: Result<u32, &str> = Ok(2);
        let y = x.map(|v| v * 2);
        assert_eq!(y, Ok(4));
        
        let x: Result<u32, &str> = Err("error");
        let y = x.map(|v| v * 2);
        assert_eq!(y, Err("error"));
    }

    #[test]
    fn test_map_err() {
        let x: Result<u32, String> = Ok(2);
        let y = x.map_err(|e| e.to_uppercase());
        assert_eq!(y, Ok(2));
        
        let x: Result<u32, String> = Err(String::from("error"));
        let y = x.map_err(|e| e.to_uppercase());
        assert_eq!(y, Err(String::from("ERROR")));
    }

    #[test]
    fn test_map_or() {
        let x: Result<&str, &str> = Ok("foo");
        assert_eq!(x.map_or(42, |v| v.len()), 3);
        
        let x: Result<&str, &str> = Err("bar");
        assert_eq!(x.map_or(42, |v| v.len()), 42);
    }

    #[test]
    fn test_map_or_else() {
        let k = 21;
        
        let x: Result<&str, &str> = Ok("foo");
        assert_eq!(x.map_or_else(|e| k * 2, |v| v.len()), 3);
        
        let x: Result<&str, &str> = Err("bar");
        assert_eq!(x.map_or_else(|e| k * 2, |v| v.len()), 42);
    }

    // inspect系のテスト (Rust 1.47.0+)
    #[test]
    fn test_inspect() {
        let mut captured = 0;
        
        let x: Result<u32, &str> = Ok(4);
        let y = x.inspect(|v| captured = *v);
        assert_eq!(captured, 4);
        assert_eq!(y, Ok(4));
        
        captured = 0;
        let x: Result<u32, &str> = Err("error");
        let y = x.inspect(|v| captured = *v);
        assert_eq!(captured, 0); // not called
        assert_eq!(y, Err("error"));
    }

    #[test]
    fn test_inspect_err() {
        let mut captured = String::new();
        
        let x: Result<u32, &str> = Err("error");
        let y = x.inspect_err(|e| captured = e.to_string());
        assert_eq!(captured, "error");
        assert_eq!(y, Err("error"));
        
        captured.clear();
        let x: Result<u32, &str> = Ok(4);
        let y = x.inspect_err(|e| captured = e.to_string());
        assert_eq!(captured, ""); // not called
        assert_eq!(y, Ok(4));
    }

    // and/or系のテスト
    #[test]
    fn test_and() {
        let x: Result<u32, &str> = Ok(2);
        let y: Result<&str, &str> = Err("late error");
        assert_eq!(x.and(y), Err("late error"));
        
        let x: Result<u32, &str> = Err("early error");
        let y: Result<&str, &str> = Ok("foo");
        assert_eq!(x.and(y), Err("early error"));
        
        let x: Result<u32, &str> = Ok(2);
        let y: Result<&str, &str> = Ok("different result type");
        assert_eq!(x.and(y), Ok("different result type"));
    }

    #[test]
    fn test_and_then() {
        fn sq_then_to_string(x: u32) -> Result<String, &'static str> {
            x.checked_mul(x).map(|sq| sq.to_string()).ok_or("overflowed")
        }
        
        assert_eq!(Ok(2).and_then(sq_then_to_string), Ok(4.to_string()));
        assert_eq!(Ok(1_000_000).and_then(sq_then_to_string), Err("overflowed"));
        assert_eq!(Err("not a number").and_then(sq_then_to_string), Err("not a number"));
    }

    #[test]
    fn test_or() {
        let x: Result<u32, &str> = Ok(2);
        let y: Result<u32, &str> = Err("late error");
        assert_eq!(x.or(y), Ok(2));
        
        let x: Result<u32, &str> = Err("early error");
        let y: Result<u32, &str> = Ok(2);
        assert_eq!(x.or(y), Ok(2));
        
        let x: Result<u32, &str> = Err("not a 2");
        let y: Result<u32, &str> = Err("not a 2");
        assert_eq!(x.or(y), Err("not a 2"));
    }

    #[test]
    fn test_or_else() {
        fn sq(x: u32) -> Result<u32, u32> { Ok(x * x) }
        fn err(x: u32) -> Result<u32, u32> { Err(x) }
        
        assert_eq!(Ok(2).or_else(sq).or_else(sq), Ok(2));
        assert_eq!(Ok(2).or_else(err).or_else(sq), Ok(2));
        assert_eq!(Err(3).or_else(sq).or_else(err), Ok(9));
        assert_eq!(Err(3).or_else(err).or_else(err), Err(3));
    }

    // ok/err変換のテスト
    #[test]
    fn test_ok() {
        let x: Result<u32, &str> = Ok(2);
        assert_eq!(x.ok(), Some(2));
        
        let x: Result<u32, &str> = Err("Nothing here");
        assert_eq!(x.ok(), None);
    }

    #[test]
    fn test_err() {
        let x: Result<u32, &str> = Ok(2);
        assert_eq!(x.err(), None);
        
        let x: Result<u32, &str> = Err("Nothing here");
        assert_eq!(x.err(), Some("Nothing here"));
    }

    // transpose のテスト
    #[test]
    fn test_transpose() {
        let x: Result<Option<i32>, &str> = Ok(Some(5));
        let y: Option<Result<i32, &str>> = Some(Ok(5));
        assert_eq!(x.transpose(), y);
        
        let x: Result<Option<i32>, &str> = Ok(None);
        let y: Option<Result<i32, &str>> = None;
        assert_eq!(x.transpose(), y);
    }

    // flatten のテスト (Rust 1.70.0+)
    #[test]
    fn test_flatten() {
        let x: Result<Result<&str, u32>, u32> = Ok(Ok("hello"));
        assert_eq!(x.flatten(), Ok("hello"));
        
        let x: Result<Result<&str, u32>, u32> = Ok(Err(6));
        assert_eq!(x.flatten(), Err(6));
        
        let x: Result<Result<&str, u32>, u32> = Err(6);
        assert_eq!(x.flatten(), Err(6));
    }

    // iter系のテスト
    #[test]
    fn test_iter() {
        let x: Result<u32, &str> = Ok(7);
        let mut iter = x.iter();
        assert_eq!(iter.next(), Some(&7));
        assert_eq!(iter.next(), None);
        
        let x: Result<u32, &str> = Err("nothing!");
        let mut iter = x.iter();
        assert_eq!(iter.next(), None);
    }

    #[test]
    fn test_iter_mut() {
        let mut x: Result<u32, &str> = Ok(7);
        match x.iter_mut().next() {
            Some(v) => *v = 40,
            None => {},
        }
        assert_eq!(x, Ok(40));
        
        let mut x: Result<u32, &str> = Err("nothing!");
        for v in x.iter_mut() {
            *v = 0; // never executed
        }
        assert_eq!(x, Err("nothing!"));
    }

    // expect系のテスト
    #[test]
    fn test_expect() {
        let x: Result<u32, &str> = Ok(2);
        assert_eq!(x.expect("Testing expect"), 2);
    }

    #[test]
    #[should_panic(expected = "Testing expect_err: 2")]
    fn test_expect_err() {
        let x: Result<u32, &str> = Ok(2);
        x.expect_err("Testing expect_err"); // panics with `Testing expect_err: 2`
    }

    // contains のテスト (Rust 1.59.0+)
    #[test]
    fn test_contains() {
        let x: Result<u32, &str> = Ok(2);
        assert_eq!(x.contains(&2), true);
        assert_eq!(x.contains(&3), false);
        
        let x: Result<u32, &str> = Err("Some error message");
        assert_eq!(x.contains(&2), false);
    }

    #[test]
    fn test_contains_err() {
        let x: Result<u32, &str> = Ok(2);
        assert_eq!(x.contains_err(&"Some error message"), false);
        
        let x: Result<u32, &str> = Err("Some error message");
        assert_eq!(x.contains_err(&"Some error message"), true);
        assert_eq!(x.contains_err(&"Some other message"), false);
    }

    // copied のテスト
    #[test]
    fn test_copied() {
        let val = 12;
        let x: Result<&i32, &str> = Ok(&val);
        assert_eq!(x.copied(), Ok(12));
        
        let x: Result<&i32, &str> = Err("error");
        assert_eq!(x.copied(), Err("error"));
    }

    // cloned のテスト
    #[test]
    fn test_cloned() {
        let val = String::from("Hello");
        let x: Result<&String, &str> = Ok(&val);
        assert_eq!(x.cloned(), Ok(String::from("Hello")));
        
        let x: Result<&String, &str> = Err("error");
        assert_eq!(x.cloned(), Err("error"));
    }

    // as_ref / as_mut のテスト
    #[test]
    fn test_as_ref() {
        let x: Result<u32, &str> = Ok(2);
        assert_eq!(x.as_ref(), Ok(&2));
        
        let x: Result<u32, &str> = Err("Error");
        assert_eq!(x.as_ref(), Err(&"Error"));
    }

    #[test]
    fn test_as_mut() {
        let mut x: Result<u32, &str> = Ok(2);
        match x.as_mut() {
            Ok(v) => *v = 42,
            Err(_) => {},
        }
        assert_eq!(x, Ok(42));
        
        let mut x: Result<u32, &str> = Err("Error");
        match x.as_mut() {
            Ok(_) => {},
            Err(e) => *e = "New error",
        }
        assert_eq!(x, Err("New error"));
    }

    // ? 演算子のテスト
    #[test]
    fn test_question_mark_operator() -> Result<(), &'static str> {
        fn try_to_parse() -> Result<i32, &'static str> {
            let x: Result<i32, &'static str> = Ok(5);
            let y = x?; // ? 演算子でエラーを早期リターン
            Ok(y * 2)
        }
        
        assert_eq!(try_to_parse(), Ok(10));
        
        fn try_to_parse_err() -> Result<i32, &'static str> {
            let x: Result<i32, &'static str> = Err("failed");
            let y = x?; // ここでエラーが返される
            Ok(y * 2) // ここには到達しない
        }
        
        assert_eq!(try_to_parse_err(), Err("failed"));
        
        Ok(())
    }

    // チェインのテスト
    #[test]
    fn test_chaining() {
        let result = Ok::<_, &str>(2)
            .map(|x| x * 2)
            .and_then(|x| Ok(x + 1))
            .map(|x| x.to_string());
        
        assert_eq!(result, Ok(String::from("5")));
        
        let result = Err::<i32, _>("initial error")
            .map(|x| x * 2)
            .or_else(|_| Ok(10))
            .map(|x| x + 1);
        
        assert_eq!(result, Ok(11));
    }

    // FromIterator のテスト
    #[test]
    fn test_from_iterator() {
        let results = vec![Ok(1), Ok(2), Ok(3)];
        let result: Result<Vec<_>, &str> = results.into_iter().collect();
        assert_eq!(result, Ok(vec![1, 2, 3]));
        
        let results = vec![Ok(1), Err("error"), Ok(3)];
        let result: Result<Vec<_>, &str> = results.into_iter().collect();
        assert_eq!(result, Err("error"));
    }

    // パターンマッチングのテスト
    #[test]
    fn test_pattern_matching() {
        let result: Result<i32, &str> = Ok(5);
        
        let value = match result {
            Ok(v) => v * 2,
            Err(_) => 0,
        };
        assert_eq!(value, 10);
        
        let result: Result<i32, &str> = Err("error");
        let value = match result {
            Ok(v) => v * 2,
            Err(e) => e.len() as i32,
        };
        assert_eq!(value, 5);
    }

    // if let のテスト
    #[test]
    fn test_if_let() {
        let result: Result<i32, &str> = Ok(5);
        
        let mut value = 0;
        if let Ok(v) = result {
            value = v;
        }
        assert_eq!(value, 5);
        
        let result: Result<i32, &str> = Err("error");
        value = 0;
        if let Err(e) = result {
            value = e.len() as i32;
        }
        assert_eq!(value, 5);
    }
}