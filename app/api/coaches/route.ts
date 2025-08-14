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

      return NextResponse.json(
        {
          error: "Failed to fetch coaches from WordPress",
          details: `WordPress API returned ${response.status}`,
          coaches: [],
        },
        { status: response.status },
      )
    }

    const data = await response.json()
    console.log(`[Next.js API] WordPress coaches response:`, data)

    const transformedCoaches = (data.coaches || []).map((coach: any) => ({
      id: coach.id,
      code: coach.code,
      name: coach.code, // Use code as name for consistency
      type: coach.type,
      type_name: coach.type_name,
      total_seats: coach.total_seats,
      front_facing_seats: coach.front_facing_seats || [],
      back_facing_seats: coach.back_facing_seats || [],
    }))

    return NextResponse.json({
      coaches: transformedCoaches,
      total: data.total || transformedCoaches.length,
    })
  } catch (error) {
    console.error("[Next.js API] Coaches API error:", error)

    return NextResponse.json(
      {
        error: "Failed to fetch coaches",
        details: error instanceof Error ? error.message : "Unknown error",
        coaches: [],
      },
      { status: 500 },
    )
  }
}
