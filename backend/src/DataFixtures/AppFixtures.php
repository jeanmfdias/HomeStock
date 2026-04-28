<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\StorageLocation;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(private readonly UserPasswordHasherInterface $hasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $categories = [
            ['Market', 'market', true],
            ['Vegetables & Fruits', 'vegetables-fruits', true],
            ['Meat', 'meat', true],
            ['Beverages', 'beverages', true],
            ['Bakery', 'bakery', true],
            ['Frozen', 'frozen', true],
            ['Medicine', 'medicine', true],
            ['Cleaning', 'cleaning', false],
            ['Hygiene', 'hygiene', false],
            ['Pet', 'pet', false],
            ['Car', 'car', false],
        ];
        foreach ($categories as [$name, $slug, $req]) {
            $manager->persist(new Category($name, $slug, $req));
        }

        foreach (['Pantry', 'Fridge', 'Freezer', 'Bathroom', 'Garage'] as $loc) {
            $manager->persist(new StorageLocation($loc));
        }

        $demo = new User('demo@homestock.local', 'Demo User');
        $demo->setPassword($this->hasher->hashPassword($demo, 'demopass123'));
        $manager->persist($demo);

        $manager->flush();
    }
}
