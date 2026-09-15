#!/bin/bash
echo "=========================================="
echo "HORION TIME - Test Suite"
echo "=========================================="
echo ""

echo "🧪 Ejecutando tests unitarios..."
echo ""

php tests/Unit/JWTTest.php
php tests/Unit/SecurityTest.php

echo ""
echo "=========================================="
echo "✅ Suite de tests completada"
echo "=========================================="