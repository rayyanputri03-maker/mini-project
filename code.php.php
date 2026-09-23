<?php
// 1. Inisialisasi Database SQLite
$dbFile = __DIR__ . '/products.db';

try {
    $pdo = new PDO("sqlite:" . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Buat tabel jika belum ada
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sku TEXT UNIQUE NOT NULL,
            name TEXT NOT NULL,
            category TEXT NOT NULL,
            price REAL NOT NULL,
            stock INTEGER NOT NULL,
            description TEXT
        )
    ");
} catch (PDOException $e) {
    die("Koneksi Database Gagal: " . $e->getMessage());
}

// 2. Routing REST API
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    $action =$_GET['api'];

    // READ: Ambil Semua Produk
    if ($action === 'get_products') {
        $stmt =$pdo->query("SELECT * FROM products ORDER BY id DESC");
        echo json_encode($stmt->fetchAll());
        exit;
    }

    // CREATE: Tambah Produk Baru
    if ($action === 'create_product' && $_SERVER['REQUEST_METHOD'] === 'POST') {$input = json_decode(file_get_contents('php://input'), true);

        if (!$input \vert{}\vert{} empty($input['sku']) || empty($input['name']) \vert{}\vert{} empty($input['category'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Data wajib diisi belum lengkap']);
            exit;
        }

        try {
            $stmt =$pdo->prepare("INSERT INTO products (sku, name, category, price, stock, description) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$input['sku'],
                $input['name'],$input['category'],
                (float)$input['price'],
                (int)$input['stock'],$input['description'] ?? ''
            ]);
            http_response_code(201);
            echo json_encode(['message' => 'Produk berhasil dibuat']);
        } catch (PDOException $e) {
            http_response_code(409);
            echo json_encode(['error' => 'SKU sudah terdaftar']);
        }
        exit;
    }

    // UPDATE: Perbarui Produk
    if ($action === 'update_product' &&$_SERVER['REQUEST_METHOD'] === 'POST') {
        $id =$_GET['id'] ?? null;
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$id \vert{}\vert{} !$input) {
            http_response_code(400);
            echo json_encode(['error' => 'ID atau data tidak valid']);
            exit;
        }

        try {
            $stmt =$pdo->prepare("UPDATE products SET sku=?, name=?, category=?, price=?, stock=?, description=? WHERE id=?");
            $stmt->execute([$input['sku'],
                $input['name'],$input['category'],
                (float)$input['price'],
                (int)$input['stock'],
                $input['description'] ?? '',$id
            ]);
            echo json_encode(['message' => 'Produk berhasil diperbarui']);
        } catch (PDOException $e) {
            http_response_code(409);
            echo json_encode(['error' => 'SKU sudah digunakan produk lain']);
        }
        exit;
    }

    // DELETE: Hapus Produk
    if ($action === 'delete_product' &&$_SERVER['REQUEST_METHOD'] === 'POST') {
        $id =$_GET['id'] ?? null;
        if ($id) {
            $stmt =$pdo->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['message' => 'Produk berhasil dihapus']);
        }
        exit;
    }
}
?>

<!-- 3. Tampilan Antarmuka (HTML + Tailwind CSS + JavaScript) -->
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Information System (PHP)</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6">
    <div class="max-w-6xl mx-auto">
        <h1 class="text-3xl font-bold mb-6 text-gray-800">Product Information System (PHP)</h1>
        
        <!-- Form Input Produk -->
        <div class="bg-white p-6 rounded-lg shadow-md mb-8">
            <h2 id="form-title" class="text-xl font-semibold mb-4 text-gray-700">Tambah Produk Baru</h2>
            <form id="product-form" onsubmit="handleSubmit(event)" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <input type="hidden" id="product-id">
                <div>
                    <label class="block text-sm font-medium text-gray-700">SKU</label>
                    <input type="text" id="sku" required class="mt-1 w-full p-2 border rounded-md">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Nama Produk</label>
                    <input type="text" id="name" required class="mt-1 w-full p-2 border rounded-md">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Kategori</label>
                    <input type="text" id="category" required class="mt-1 w-full p-2 border rounded-md">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Harga (Rp)</label>
                    <input type="number" step="0.01" id="price" required class="mt-1 w-full p-2 border rounded-md">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Stok</label>
                    <input type="number" id="stock" required class="mt-1 w-full p-2 border rounded-md">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Deskripsi</label>
                    <input type="text" id="description" class="mt-1 w-full p-2 border rounded-md">
                </div>
                <div class="md:col-span-3 flex gap-2">
                    <button type="submit" id="submit-btn" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">Simpan Produk</button>
                    <button type="button" onclick="resetForm()" class="bg-gray-400 text-white px-4 py-2 rounded-md hover:bg-gray-500">Batal</button>
                </div>
            </form>
        </div>

        <!-- Tabel Daftar Produk -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-semibold mb-4 text-gray-700">Daftar Produk</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-200 text-gray-700">
                            <th class="p-3">ID</th>
                            <th class="p-3">SKU</th>
                            <th class="p-3">Nama</th>
                            <th class="p-3">Kategori</th>
                            <th class="p-3">Harga</th>
                            <th class="p-3">Stok</th>
                            <th class="p-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="product-table-body"></tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        async function fetchProducts() {
            const res = await fetch('index.php?api=get_products');
            const products = await res.json();
            const tbody = document.getElementById('product-table-body');
            tbody.innerHTML = '';
            
            products.forEach(p => {
                tbody.innerHTML += `
                    <tr class="border-b">
                        <td class="p-3">${p.id}</td>
                        <td class="p-3 font-semibold">${p.sku}</td>
                        <td class="p-3">${p.name}</td>
                        <td class="p-3">${p.category}</td>
                        <td class="p-3">Rp ${Number(p.price).toLocaleString('id-ID')}</td>
                        <td class="p-3">${p.stock}</td>
                        <td class="p-3 flex gap-2">
                            <button onclick='editProduct(${JSON.stringify(p)})' class="bg-yellow-500 text-white px-3 py-1 rounded text-sm hover:bg-yellow-600">Edit</button>
                            <button onclick="deleteProduct(${p.id})" class="bg-red-600 text-white px-3 py-1 rounded text-sm hover:bg-red-700">Hapus</button>
                        </td>
                    </tr>
                `;
            });
        }

        async function handleSubmit(e) {
            e.preventDefault();
            const id = document.getElementById('product-id').value;
            const payload = {
                sku: document.getElementById('sku').value,
                name: document.getElementById('name').value,
                category: document.getElementById('category').value,
                price: parseFloat(document.getElementById('price').value),
                stock: parseInt(document.getElementById('stock').value),
                description: document.getElementById('description').value
            };

            const endpoint = id ? `index.php?api=update_product&id=${id}` : 'index.php?api=create_product';

            const res = await fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            if (res.ok) {
                resetForm();
                fetchProducts();
            } else {
                const err = await res.json();
                alert(err.error || 'Terjadi kesalahan');
            }
        }

        function editProduct(p) {
            document.getElementById('product-id').value = p.id;
            document.getElementById('sku').value = p.sku;
            document.getElementById('name').value = p.name;
            document.getElementById('category').value = p.category;
            document.getElementById('price').value = p.price;
            document.getElementById('stock').value = p.stock;
            document.getElementById('description').value = p.description || '';
            document.getElementById('form-title').innerText = 'Edit Produk';
            document.getElementById('submit-btn').innerText = 'Update Produk';
        }

        async function deleteProduct(id) {
            if (confirm('Yakin ingin menghapus produk ini?')) {
                await fetch(`index.php?api=delete_product&id=${id}`, { method: 'POST' });
                fetchProducts();
            }
        }

        function resetForm() {
            document.getElementById('product-form').reset();
            document.getElementById('product-id').value = '';
            document.getElementById('form-title').innerText = 'Tambah Produk Baru';
            document.getElementById('submit-btn').innerText = 'Simpan Produk';
        }

        fetchProducts();
    </script>
</body>
</html>