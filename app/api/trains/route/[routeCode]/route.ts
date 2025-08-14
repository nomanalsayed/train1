
import { type NextRequest, NextResponse } from "next/server"
import { API_CONFIG } from "@/lib/config"

export async function GET(request: NextRequest, { params }: { params: Promise<{ routeCode: string }> }) {
  try {
    const resolvedParams = await params
    const routeCode = resolvedParams.routeCode
    const { searchParams } = new URL(request.url)
    const direction = searchParams.get('direction') || 'forward'

    console.log(`[Next.js API] Fetching train by route code ${routeCode}, direction: ${direction}`)

    const wpUrl = new URL(`${API_CONFIG.RAIL_BASE_URL}/trains/route/${routeCode}`)
    wpUrl.searchParams.set('direction', direction)

    console.log(`[Next.js API] WordPress API URL: ${wpUrl.toString()}`)

    const controller = new AbortController()
    const timeoutId = setTimeout(() => controller.abort(), 10000)

    const response = await fetch(wpUrl.toString(), {
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
      console.error(`[Next.js API] WordPress API error: ${response.status}`, errorText)

      return NextResponse.json(
        {
          error: "Train not found for route code",
          message: `No train found for route code ${routeCode}`,
          routeCode,
          direction,
        },
        { status: 404 },
      )
    }

    const data = await response.json()
    console.log(`[Next.js API] Train data for route ${routeCode}:`, data)

    return NextResponse.json({
      ...data,
      routeCode,
      direction,
    })
  } catch (error) {
    console.error("[Next.js API] Error fetching train by route code:", error)
    const resolvedParams = await params
    return NextResponse.json(
      {
        error: "Failed to fetch train by route code",
        message: error instanceof Error ? error.message : "Unknown error",
        routeCode: resolvedParams.routeCode,
      },
      { status: 500 },
    )
  }
}
