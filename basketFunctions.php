<?php
# Manages the basket, adding, updating and removing items, validating contents, calculating totals #
# Security Measures. #
# Session based basket management with error handling. #


function add($db, $id) {
    try {
        if (!isset($_SESSION['basket'])) {
            $_SESSION['basket'] = array();
        }

        $stmt = $db->connect()->prepare("
            SELECT id, name, price, image, stock 
            FROM tbl_products 
            WHERE id = :id AND active = 1 AND stock > 0
        ");
        
        $stmt->execute([':id' => $id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            return false;
        }

        $currentQuantity = isset($_SESSION['basket'][$id]['quantity']) ? $_SESSION['basket'][$id]['quantity'] : 0;
        
        if ($currentQuantity >= $product['stock']) {
            return false;
        }

        if (isset($_SESSION['basket'][$id])) {
            $_SESSION['basket'][$id]['quantity']++;
        } else {
            $_SESSION['basket'][$id] = array(
                'id' => $product['id'],
                'name' => $product['name'],
                'price' => $product['price'],
                'image' => $product['image'],
                'quantity' => 1
            );
        }
        return true;
    } catch(Exception $e) {
        error_log("Add to basket error: " . $e->getMessage());
        return false;
    }
}

function updateQuantity($id, $quantity) {
    if (!isset($_SESSION['basket'][$id])) {
        return false;
    }

    try {
        $db = new DB();
        $stmt = $db->connect()->prepare("
            SELECT stock FROM tbl_products 
            WHERE id = :id AND active = 1
        ");
        $stmt->execute([':id' => $id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product || $quantity > $product['stock']) {
            return false;
        }

        if ($quantity <= 0) {
            unset($_SESSION['basket'][$id]);
        } else {
            $_SESSION['basket'][$id]['quantity'] = $quantity;
        }
        return true;
    } catch(Exception $e) {
        error_log("Update quantity error: " . $e->getMessage());
        return false;
    }
}

function removeItem($id) {
    if (!isset($_SESSION['basket'][$id])) {
        return false;
    }
    unset($_SESSION['basket'][$id]);
    return true;
}

function emptyBasket() {
    $_SESSION['basket'] = array();
    return true;
}

function getBasketItems() {
    if (!isset($_SESSION['basket'])) {
        return array();
    }

    $db = new DB();
    $validItems = array();

    foreach ($_SESSION['basket'] as $id => $item) {
        $stmt = $db->connect()->prepare("
            SELECT id, name, price, image, stock 
            FROM tbl_products 
            WHERE id = :id AND active = 1 AND stock >= :quantity
        ");
        
        $stmt->execute([
            ':id' => $id,
            ':quantity' => $item['quantity']
        ]);
        
        if ($product = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $validItems[$id] = $item;
        }
    }

    $_SESSION['basket'] = $validItems;
    return $validItems;
}

function getBasketTotal() {
    $total = 0;
    $items = getBasketItems();
    foreach ($items as $item) {
        $total += $item['price'] * $item['quantity'];
    }
    return $total;
}

function getBasketItemCount() {
    $items = getBasketItems();
    return array_sum(array_column($items, 'quantity'));
}

function validateBasket($db) {
    if (!isset($_SESSION['basket']) || empty($_SESSION['basket'])) {
        return false;
    }

    foreach ($_SESSION['basket'] as $id => $item) {
        $stmt = $db->connect()->prepare("
            SELECT stock FROM tbl_products 
            WHERE id = :id AND active = 1 AND stock >= :quantity
        ");
        
        $stmt->execute([
            ':id' => $id,
            ':quantity' => $item['quantity']
        ]);
        
        if (!$stmt->fetch()) {
            return false;
        }
    }
    return true;
}

function calculateTotal($items) {
    $total = 0;
    foreach ($items as $item) {
        $total += $item['price'] * $item['quantity'];
    }
    return $total;
}
?>