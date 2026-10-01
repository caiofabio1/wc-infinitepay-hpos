## 2024-05-24 - [Transient Key Collision and Delimiter Vulnerability]
**Vulnerability:** Transient keys generated using hashes of multiple user-controlled variables (e.g., transaction NSU and slug) used `md5` and lacked string delimitation, leading to possible collision risks in caching and rate limiting checks.
**Learning:** Relying on weak hashing combinations without delimiters can lead to key collision vulnerabilities in WordPress transients, potentially allowing attackers to bypass rate limits or serve incorrect cached responses.
**Prevention:** Use `sha256` for key hashing and always include a structured delimiter (like a pipe `|`) when concatenating multiple variables before hashing to prevent concatenation collisions.
