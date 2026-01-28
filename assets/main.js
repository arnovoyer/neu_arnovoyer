// LENIS
const lenis = new Lenis({
  duration: 1.1,
  smoothWheel: true
})

function raf(time) {
  lenis.raf(time)
  requestAnimationFrame(raf)
}
requestAnimationFrame(raf)

// VANTA
VANTA.NET({
  el: "#vanta",
  color: 0x00f5d4,
  backgroundColor: 0x0a0a0a,
  points: 10,
  maxDistance: 22
})

// HERO ANIMATION
gsap.from(".hero-title", {
  y: 80,
  opacity: 0,
  duration: 1.2,
  ease: "power4.out"
})

gsap.from(".hero-sub", {
  y: 40,
  opacity: 0,
  delay: 0.2,
  duration: 1,
  ease: "power4.out"
})

// SCROLL REVEAL
gsap.from(".about-text", {
  scrollTrigger: {
    trigger: ".about-text",
    start: "top 80%"
  },
  y: 40,
  opacity: 0,
  duration: 1,
  ease: "power3.out"
})
const canvas = document.getElementById("three-canvas")

const scene = new THREE.Scene()
const camera = new THREE.PerspectiveCamera(
    45,
    window.innerWidth / window.innerHeight,
    0.1,
    100
)
camera.position.z = 6

const renderer = new THREE.WebGLRenderer({
    canvas,
    alpha: true,
    antialias: true
})
renderer.setSize(window.innerWidth, window.innerHeight)
renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2))

const geometry = new THREE.IcosahedronGeometry(1.5, 0)
const material = new THREE.MeshStandardMaterial({
    color: 0x00f5d4,
    wireframe: true
})

const mesh = new THREE.Mesh(geometry, material)
scene.add(mesh)

const light = new THREE.DirectionalLight(0xffffff, 1)
light.position.set(5, 5, 5)
scene.add(light)

function animate() {
    mesh.rotation.x += 0.002
    mesh.rotation.y += 0.003
    renderer.render(scene, camera)
    requestAnimationFrame(animate)
}
animate()

window.addEventListener("resize", () => {
    camera.aspect = window.innerWidth / window.innerHeight
    camera.updateProjectionMatrix()
    renderer.setSize(window.innerWidth, window.innerHeight)
})
document.getElementById("logo").addEventListener("dblclick", () => {
  document.documentElement.style.setProperty(
    "--accent",
    "#" + Math.floor(Math.random()*16777215).toString(16)
  )
})
