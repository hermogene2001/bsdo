package com.bsdosale

import android.content.Intent
import android.os.Bundle
import android.view.MenuItem
import androidx.appcompat.app.AppCompatActivity
import androidx.fragment.app.Fragment
import com.google.android.material.bottomnavigation.BottomNavigationView

class HomeActivity : AppCompatActivity() {
    
    private lateinit var bottomNavigation: BottomNavigationView
    
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_home)
        
        bottomNavigation = findViewById(R.id.bottom_navigation)
        bottomNavigation.setOnItemSelectedListener { item ->
            when (item.itemId) {
                R.id.nav_home -> {
                    loadFragment(HomeFragment())
                    true
                }
                R.id.nav_products -> {
                    loadFragment(ProductsFragment())
                    true
                }
                R.id.nav_live_streams -> {
                    loadFragment(LiveStreamsFragment())
                    true
                }
                R.id.nav_account -> {
                    loadFragment(AccountFragment())
                    true
                }
                else -> false
            }
        }
        
        // Load default fragment
        if (savedInstanceState == null) {
            loadFragment(HomeFragment())
        }
    }
    
    private fun loadFragment(fragment: Fragment) {
        supportFragmentManager.beginTransaction()
            .replace(R.id.fragment_container, fragment)
            .commit()
    }
}