const express = require("express");
const mysql = require("mysql2");
const cors = require("cors");
const jwt = require("jsonwebtoken");
const bcrypt = require("bcrypt");

const app = express();
app.use(cors());
app.use(express.json());

const SECRET_KEY = "mysecretkey";

// 🔗 Kết nối MySQL
const db = mysql.createConnection({
  host: "localhost",
  user: "root",
  password: "ngocbenho",
  database: "webhoctap"
});

db.connect(err => {
  if (err) {
    console.log("❌ Lỗi MySQL:", err);
  } else {
    console.log("✅ Kết nối MySQL thành công!");
  }
});


// =======================
// 🧑‍💻 ĐĂNG KÝ
// =======================
app.post("/register", async (req, res) => {
  const { username, password, role } = req.body;

  if (!username || !password || !role) {
    return res.status(400).send("Thiếu dữ liệu!");
  }

  const hash = await bcrypt.hash(password, 10);

  const sql = "INSERT INTO nguoidung (username, password, role) VALUES (?, ?, ?)";
  db.query(sql, [username, hash, role], (err) => {
    if (err) {
      console.log(err);
      return res.status(500).send("Lỗi đăng ký");
    }
    res.send("Đăng ký thành công!");
  });
});


// =======================
// 🔐 ĐĂNG NHẬP
// =======================
app.post("/login", (req, res) => {
  const { username, password } = req.body;

  const sql = "SELECT * FROM nguoidung WHERE username = ?";
  db.query(sql, [username], async (err, result) => {
    if (err) return res.status(500).send(err);

    if (result.length === 0) {
      return res.status(401).send("Sai tài khoản!");
    }

    const user = result[0];

    const isMatch = await bcrypt.compare(password, user.password);

    if (!isMatch) {
      return res.status(401).send("Sai mật khẩu!");
    }

    // tạo token
    const token = jwt.sign(
      { id: user.id, role: user.role },
      SECRET_KEY,
      { expiresIn: "1h" }
    );

    res.json({
      message: "Đăng nhập thành công!",
      token,
      role: user.role
    });
  });
});


// =======================
// 🔒 MIDDLEWARE CHECK LOGIN
// =======================
function verifyToken(req, res, next) {
  const token = req.headers["authorization"];

  if (!token) return res.status(403).send("Không có token!");

  jwt.verify(token, SECRET_KEY, (err, decoded) => {
    if (err) return res.status(401).send("Token không hợp lệ!");

    req.user = decoded;
    next();
  });
}


// =======================
// 👑 CHECK ADMIN
// =======================
function isAdmin(req, res, next) {
  if (req.user.role !== "admin") {
    return res.status(403).send("Không có quyền admin!");
  }
  next();
}


// =======================
// 👨‍🏫 CHECK GIÁO VIÊN
// =======================
function isTeacher(req, res, next) {
  if (req.user.role !== "giaovien") {
    return res.status(403).send("Không phải giáo viên!");
  }
  next();
}


// =======================
// 👨‍🎓 CHECK HỌC SINH
// =======================
function isStudent(req, res, next) {
  if (req.user.role !== "hocsinh") {
    return res.status(403).send("Không phải học sinh!");
  }
  next();
}


// =======================
// 📘 GIÁO VIÊN TẠO BÀI TẬP
// =======================
app.post("/baitap", verifyToken, isTeacher, (req, res) => {
  const { noidung } = req.body;

  const sql = "INSERT INTO baitap (noidung, giaovien_id) VALUES (?, ?)";

  db.query(sql, [noidung, req.user.id], (err) => {
    if (err) return res.status(500).send(err);

    res.send("Tạo bài tập thành công!");
  });
});


// =======================
// 📖 HỌC SINH XEM BÀI TẬP
// =======================
app.get("/baitap", verifyToken, (req, res) => {
  db.query("SELECT * FROM baitap", (err, result) => {
    if (err) return res.status(500).send(err);
    res.json(result);
  });
});


// =======================
// 📝 HỌC SINH NỘP BÀI
// =======================
app.post("/nopbai", verifyToken, isStudent, (req, res) => {
  const { baitap_id, noidung } = req.body;

  const sql = "INSERT INTO nopbai (baitap_id, hocsinh_id, noidung) VALUES (?, ?, ?)";

  db.query(sql, [baitap_id, req.user.id, noidung], (err) => {
    if (err) return res.status(500).send(err);

    res.send("Nộp bài thành công!");
  });
});


// =======================
// 👑 ADMIN XEM USER
// =======================
app.get("/users", verifyToken, isAdmin, (req, res) => {
  db.query("SELECT id, username, role FROM nguoidung", (err, result) => {
    if (err) return res.status(500).send(err);
    res.json(result);
  });
});


// =======================
// 🚀 CHẠY SERVER
// =======================
app.listen(3000, () => {
  console.log("🚀 Server chạy tại http://localhost:3000");
});