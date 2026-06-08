<?php

function applyPurchasedInventory($conn, int $productId, int $quantity): bool {
    $productId = max(0, $productId);
    $quantity = max(1, $quantity);

    $stmt = $conn->prepare("UPDATE products SET stock = GREATEST(stock - ?, 0) WHERE id = ? AND stock >= ?");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("iii", $quantity, $productId, $quantity);
    $stmt->execute();
    $ok = $stmt->affected_rows > 0;
    $stmt->close();

    return $ok;
}
?>
