
import { type NextRequest, NextResponse } from "next/server"
import { API_CONFIG } from "@/lib/config"

export async function GET(request: NextRequest) {
  try {
    console.log(`[Next.js API] Fetching coaches from: ${API_CONFIG.RAIL_BASE_URL}/coaches`)

    const controller = new AbortController()
    const timeoutId = setTimeout(() => controller.abort(), 10000)

    const response = await fetch(`${API_CONFIG.RAIL_BASE_URL}/coaches`, {
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        "User-Agent": "Bangladesh-Railway-App/1.0",
      },
      signal: controller.signal,
    })

    clearTimeout(timeoutId)

    if (!response.ok) {
      const errorText = await response.text()
      console.error(`[Next.js API] WordPress coaches API error: ${response.status}`)
      console.error(`[Next.js API] Error response:`, errorText)

      // Return fallback coaches
      const fallbackCoaches = [
        { id: 1, code: "UMA", name: "UMA", total_seats: 60 },
        { id: 2, code: "CHA", name: "CHA", total_seats: 80 },
        { id: 3, code: "SCHA", name: "SCHA", total_seats: 75 },
        { id: 4, code: "JHA", name: "JHA", total_seats: 70 },
        { id: 5, code: "KHA", name: "KHA", total_seats: 65 },
        { id: 6, code: "GHA", name: "GHA", total_seats: 72 },
        { id: 7, code: "TA", name: "TA", total_seats: 68 },
        { id: 8, code: "THA", name: "THA", total_seats: 74 },
        { id: 9, code: "DA", name: "DA", total_seats: 66 },
        { id: 10, code: "DHA", name: "DHA", total_seats: 71 },
      ]

      return NextResponse.json({
        coaches: fallbackCoaches,
        total: fallbackCoaches.length,
        message: "Using fallback coaches due to WordPress API error"
      })
    }

    const data = await response.json()
    console.log(`[Next.js API] WordPress coaches response:`, data)

    // Check if we have coaches data from WordPress
    let coaches = []
    if (data.coaches && Array.isArray(data.coaches) && data.coaches.length > 0) {
      coaches = data.coaches.map(coach => ({
        id: coach.id,
        code: coach.code || coach.coach_code || "",
        name: coach.name && coach.name !== coach.code 
          ? coach.name 
          : `${coach.code} Coach`,
        total_seats: coach.total_seats || 60,
        front_facing_seats: coach.front_facing_seats || [],
        back_facing_seats: coach.back_facing_seats || [],
      }))
      console.log(`[Next.js API] Found ${coaches.length} coaches from WordPress`)
    } else {
      // Fallback coaches
      coaches = [
        { id: 1, code: "UMA", name: "First Class AC Chair", total_seats: 60, front_facing_seats: [], back_facing_seats: [] },
        { id: 2, code: "CHA", name: "Chair Coach", total_seats: 80, front_facing_seats: [], back_facing_seats: [] },
        { id: 3, code: "SCHA", name: "Shuvon Chair", total_seats: 75, front_facing_seats: [], back_facing_seats: [] },
        { id: 4, code: "JHA", name: "First Class Chair", total_seats: 70, front_facing_seats: [], back_facing_seats: [] },
        { id: 5, code: "KHA", name: "Second Class Chair", total_seats: 65, front_facing_seats: [], back_facing_seats: [] },
        { id: 6, code: "GHA", name: "General Chair", total_seats: 72, front_facing_seats: [], back_facing_seats: [] },
        { id: 7, code: "TA", name: "Third Class Chair", total_seats: 68, front_facing_seats: [], back_facing_seats: [] },
        { id: 8, code: "THA", name: "Third Class Chair", total_seats: 74, front_facing_seats: [], back_facing_seats: [] },
        { id: 9, code: "DA", name: "Dhaka Chair", total_seats: 66, front_facing_seats: [], back_facing_seats: [] },
        { id: 10, code: "DHA", name: "Dhaka Chair", total_seats: 71, front_facing_seats: [], back_facing_seats: [] },
      ]
      console.log("[Next.js API] Using fallback coaches")
    }

    return NextResponse.json({
      coaches: coaches,
      total: coaches.length,
      message: coaches.length > 0 ? "Data from WordPress" : "Using fallback data"
    })

  } catch (error) {
    console.error(`[Next.js API] Error fetching coaches:`, error)

    // Return fallback coaches on error
    const fallbackCoaches = [
      { id: 1, code: "UMA", name: "UMA", total_seats: 60 },
      { id: 2, code: "CHA", name: "CHA", total_seats: 80 },
      { id: 3, code: "SCHA", name: "SCHA", total_seats: 75 },
      { id: 4, code: "JHA", name: "JHA", total_seats: 70 },
      { id: 5, code: "KHA", name: "KHA", total_seats: 65 },
      { id: 6, code: "GHA", name: "GHA", total_seats: 72 },
      { id: 7, code: "TA", name: "TA", total_seats: 68 },
      { id: 8, code: "THA", name: "THA", total_seats: 74 },
      { id: 9, code: "DA", name: "DA", total_seats: 66 },
      { id: 10, code: "DHA", name: "DHA", total_seats: 71 },
    ]

    return NextResponse.json({
      coaches: fallbackCoaches,
      total: fallbackCoaches.length,
      error: error.message,
      message: "Using fallback coaches due to error"
    })
  }
}
