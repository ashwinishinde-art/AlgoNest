USE algonest;

-- Seed Admin and normal User
-- password is 'password123' (hashed using bcrypt)
INSERT INTO users (username, email, password_hash, role) VALUES 
('admin', 'admin@dsa.com', '$2y$10$l255YVO5sby3Zb1RLL1Rb.h9ot9rYhUpEgsxVl5MBlv9t4Am1oG3i', 'admin'),
('coder', 'coder@dsa.com', '$2y$10$l255YVO5sby3Zb1RLL1Rb.h9ot9rYhUpEgsxVl5MBlv9t4Am1oG3i', 'user')
ON DUPLICATE KEY UPDATE id=id;

-- Seed Problems
INSERT INTO problems (id, title, difficulty, topic_tags, description, constraints, time_limit_sec, memory_limit_mb, author_id, approved) VALUES
(1, 'Two Sum', 'Easy', 'Arrays, Hash Map', 
'Given an array of integers `nums` and an integer `target`, return indices of the two numbers such that they add up to `target`.\n\nYou may assume that each input would have exactly one solution, and you may not use the same element twice.\n\nYou can return the answer in any order.',
'- `2 <= nums.length <= 10^4`\n- `-10^9 <= nums[i] <= 10^9`\n- `-10^9 <= target <= 10^9`\n- Only one valid answer exists.',
2.0, 256, 1, TRUE),
(2, 'Reverse String', 'Easy', 'Two Pointers, String',
'Write a function that reverses a string. The input string is given as an array of characters `s`.\n\nYou must do this by modifying the input array in-place with O(1) extra memory.',
'- `1 <= s.length <= 10^5`\n- `s[i]` is a printable ascii character.',
1.5, 256, 1, TRUE)
ON DUPLICATE KEY UPDATE id=id;

-- Seed Test Cases
INSERT INTO test_cases (problem_id, input_data, expected_output, is_sample) VALUES
-- Two Sum (Problem 1) Sample Test Cases
(1, '4 9\n2 7 11 15', '0 1', TRUE),
(1, '3 6\n3 2 4', '1 2', TRUE),
-- Two Sum (Problem 1) Hidden Test Cases
(1, '3 6\n3 3', '0 1', FALSE),
(1, '2 10\n5 5', '0 1', FALSE),

-- Reverse String (Problem 2) Sample Test Cases
(2, '5\nh e l l o', 'o l l e h', TRUE),
(2, '6\nH a n n a h', 'h a n n a H', TRUE)
ON DUPLICATE KEY UPDATE id=id;
